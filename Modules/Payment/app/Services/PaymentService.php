<?php

declare(strict_types=1);

namespace Modules\Payment\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Core\Enums\ErrorCode;
use Modules\Core\Http\Responses\ErrorResponse;
use Modules\Core\Support\ErrorLogger;
use Modules\Core\Support\PaginationMeta;
use Modules\Core\Support\SensitiveDataMasker;
use Modules\Core\Support\VersionedCache;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Models\Order;
use Modules\Payment\DTOs\PaymentAttemptData;
use Modules\Payment\Enums\PaymentStatus;
use Modules\Payment\Gateways\AbstractPaymentGateway;
use Modules\Payment\Gateways\PaymentGatewayFactory;
use Modules\Payment\Http\Resources\PaymentResource;
use Modules\Payment\Jobs\RecordPaymentAuditLogJob;
use Modules\Payment\Jobs\SendPaymentNotificationJob;
use Modules\Payment\Models\Payment;
use Throwable;

class PaymentService
{
    public function __construct(
        private readonly PaymentGatewayFactory $gateways,
        private readonly VersionedCache $cache,
        private readonly SensitiveDataMasker $masker,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function process(User $user, int $orderId, array $payload, ?string $idempotencyKey): PaymentAttemptData|ErrorResponse
    {
        $attempt = DB::transaction(function () use ($user, $orderId, $payload, $idempotencyKey): PaymentAttemptData|ErrorResponse {
            $order = Order::query()->whereKey($orderId)->lockForUpdate()->first();

            if ($order === null) {
                return ErrorResponse::logged(ErrorCode::OrderNotFound);
            }

            if ($order->user_id !== $user->id) {
                return ErrorResponse::logged(ErrorCode::Forbidden);
            }

            $hash = $this->payloadHash($order, $payload);

            if ($idempotencyKey !== null && $idempotencyKey !== '') {
                $existing = Payment::query()
                    ->where('user_id', $user->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    if ($existing->payload_hash !== $hash || $existing->order_id !== $order->id) {
                        return ErrorResponse::logged(ErrorCode::PaymentIdempotencyConflict);
                    }

                    return new PaymentAttemptData(
                        $existing->load('order'),
                        replay: true,
                        failed: $existing->status === PaymentStatus::Failed,
                    );
                }
            }

            if ($order->status !== OrderStatus::Confirmed) {
                return ErrorResponse::logged(ErrorCode::PaymentOrderNotConfirmed);
            }

            if ($order->hasSuccessfulPayment()) {
                return ErrorResponse::logged(ErrorCode::PaymentAlreadyPaid);
            }

            $gateway = $this->gateways->make((string) $payload['payment_method']);

            if ($gateway instanceof ErrorResponse) {
                return $gateway;
            }

            if ($gateway instanceof AbstractPaymentGateway) {
                $configError = $gateway->checkConfiguration();

                if ($configError !== null) {
                    return $configError;
                }

                $payloadError = $gateway->checkPayload($payload);

                if ($payloadError !== null) {
                    return $payloadError;
                }
            }

            $payment = Payment::query()->create([
                'order_id' => $order->id,
                'user_id' => $user->id,
                'payment_method' => $payload['payment_method'],
                'status' => PaymentStatus::Pending,
                'amount' => $order->total,
                'idempotency_key' => $idempotencyKey !== '' ? $idempotencyKey : null,
                'payload_hash' => $hash,
            ]);

            try {
                $result = $gateway->process($order, array_merge($payload, [
                    'idempotency_key' => $idempotencyKey ?: 'payment-'.$payment->id,
                ]));
            } catch (Throwable $exception) {
                ErrorLogger::logException($exception, ErrorCode::PaymentGatewayFailed, ['payment_id' => $payment->id]);
                $this->markFailed($payment, ErrorCode::PaymentGatewayFailed->message(), ['error_code' => ErrorCode::PaymentGatewayFailed->value]);

                return new PaymentAttemptData($payment->fresh() ?? $payment, replay: false, failed: true);
            }

            if ($result instanceof ErrorResponse) {
                if ($result->errorCode !== ErrorCode::PaymentGatewayFailed) {
                    $payment->delete();

                    return $result;
                }

                $this->markFailed($payment, ErrorCode::PaymentGatewayFailed->message(), ['error_code' => $result->errorCode->value]);

                return new PaymentAttemptData($payment->fresh() ?? $payment, replay: false, failed: true);
            }

            if (! $result->successful) {
                $this->markFailed($payment, $result->failureReason, $result->rawResponse, $result->transactionId);

                return new PaymentAttemptData($payment->fresh() ?? $payment, replay: false, failed: true);
            }

            $payment->update([
                'status' => PaymentStatus::Successful,
                'transaction_id' => $result->transactionId,
                'gateway_response' => $this->masker->mask($result->rawResponse),
                'failure_reason' => null,
            ]);

            return new PaymentAttemptData($payment->fresh() ?? $payment, replay: false, failed: false);
        });

        if ($attempt instanceof ErrorResponse) {
            return $attempt;
        }

        if (! $attempt->replay) {
            SendPaymentNotificationJob::dispatch($attempt->payment->id)->afterCommit();
            RecordPaymentAuditLogJob::dispatch($attempt->payment->id)->afterCommit();
        }

        return $attempt;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{data: array<int, mixed>, meta: array<string, mixed>}
     */
    public function list(User $user, array $filters, int $perPage): array
    {
        $tag = 'payments:user:'.$user->id;
        $key = md5(json_encode([$filters, $perPage, (int) request()->query('page', 1)]) ?: '');

        return $this->cache->remember($tag, $key, 60, function () use ($user, $filters, $perPage): array {
            $query = Payment::query()
                ->where('user_id', $user->id)
                ->recent();

            if (! empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            if (! empty($filters['payment_method'])) {
                $query->where('payment_method', $filters['payment_method']);
            }

            if (! empty($filters['order_id'])) {
                $query->where('order_id', $filters['order_id']);
            }

            $useCursor = ($filters['pagination'] ?? null) === 'cursor' || array_key_exists('cursor', $filters);
            $paginator = $useCursor
                ? $query->cursorPaginate($perPage, ['*'], 'cursor', $filters['cursor'] ?? null)
                : $query->paginate($perPage);

            return [
                'data' => PaymentResource::collection($paginator->items())->resolve(),
                'meta' => PaginationMeta::from($paginator),
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{data: array<int, mixed>, meta: array<string, mixed>}|ErrorResponse
     */
    public function listForOrder(User $user, int $orderId, array $filters, int $perPage): array|ErrorResponse
    {
        $order = Order::query()->find($orderId);

        if ($order === null) {
            return ErrorResponse::logged(ErrorCode::OrderNotFound);
        }

        if ($order->user_id !== $user->id) {
            return ErrorResponse::logged(ErrorCode::Forbidden);
        }

        return $this->list($user, array_merge($filters, ['order_id' => $orderId]), $perPage);
    }

    public function show(User $user, int $paymentId): Payment|ErrorResponse
    {
        $payment = Payment::query()->with('order')->find($paymentId);

        if ($payment === null) {
            return ErrorResponse::logged(ErrorCode::PaymentNotFound);
        }

        if ($payment->user_id !== $user->id) {
            return ErrorResponse::logged(ErrorCode::Forbidden);
        }

        return $payment;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function markFailed(Payment $payment, ?string $reason, array $response, ?string $transactionId = null): void
    {
        $payment->update([
            'status' => PaymentStatus::Failed,
            'transaction_id' => $transactionId,
            'failure_reason' => $reason,
            'gateway_response' => $this->masker->mask($response),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function payloadHash(Order $order, array $payload): string
    {
        return hash('sha256', json_encode([
            'order_id' => $order->id,
            'payment_method' => $payload['payment_method'],
            'simulate_outcome' => $payload['simulate_outcome'] ?? 'success',
            'amount' => (string) $order->total,
        ], JSON_THROW_ON_ERROR));
    }
}
