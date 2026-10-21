<?php

declare(strict_types=1);

namespace Modules\Payment\Gateways;

use Modules\Core\Enums\ErrorCode;
use Modules\Core\Http\Responses\ErrorResponse;
use Modules\Core\Support\ErrorLogger;
use Modules\Order\Models\Order;
use Modules\Payment\Contracts\PaymentGatewayInterface;
use Modules\Payment\DTOs\GatewayResponseData;
use Modules\Payment\DTOs\PaymentResultData;
use Modules\Payment\Http\Clients\GatewayHttpClient;
use Throwable;

abstract class AbstractPaymentGateway implements PaymentGatewayInterface
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        protected GatewayHttpClient $http,
        protected array $config = [],
    ) {
        if ($this->config === []) {
            $this->config = config('payment.gateways.'.$this->identifier(), []);
        }
    }

    public function isEnabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? false);
    }

    public function displayName(): string
    {
        return (string) ($this->config['display_name'] ?? $this->identifier());
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    final public function process(Order $order, array $payload = []): PaymentResultData|ErrorResponse
    {
        $configError = $this->checkConfiguration();

        if ($configError !== null) {
            return $configError;
        }

        $validationError = $this->validatePayload($payload);

        if ($validationError !== null) {
            return $validationError;
        }

        try {
            $response = $this->executeCharge($order, $this->buildChargeRequest($order, $payload), $payload);

            if ($response instanceof ErrorResponse) {
                return $response;
            }

            return $this->mapResponse($response);
        } catch (Throwable $exception) {
            ErrorLogger::logException($exception, ErrorCode::PaymentGatewayFailed, [
                'gateway' => $this->identifier(),
                'order_id' => $order->id,
            ]);

            return ErrorResponse::logged(ErrorCode::PaymentGatewayFailed);
        }
    }

    /**
     * @return list<string>
     */
    abstract protected function requiredConfigKeys(): array;

    /**
     * @param  array<string, mixed>  $preparedPayload
     * @param  array<string, mixed>  $originalPayload
     */
    abstract protected function executeCharge(Order $order, array $preparedPayload, array $originalPayload): GatewayResponseData|ErrorResponse;

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function validatePayload(array $payload): ?ErrorResponse
    {
        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function buildChargeRequest(Order $order, array $payload): array
    {
        return [
            'order_id' => $order->id,
            'amount' => $order->total,
            'currency' => $this->config['currency'] ?? 'USD',
            'simulate_outcome' => $payload['simulate_outcome'] ?? 'success',
        ];
    }

    protected function mapResponse(GatewayResponseData $response): PaymentResultData
    {
        $transactionId = (string) ($response->body['id'] ?? $response->body['transaction_id'] ?? $this->generateTransactionReference());

        return new PaymentResultData(
            successful: $response->successful,
            transactionId: $transactionId,
            gateway: $this->identifier(),
            rawResponse: $response->body,
            failureReason: $response->successful ? null : (string) ($response->body['reason'] ?? 'Payment was declined.'),
        );
    }

    protected function isSimulation(): bool
    {
        return (bool) ($this->config['simulate'] ?? false);
    }

    protected function baseUrl(): string
    {
        return rtrim((string) ($this->config['base_url'] ?? ''), '/');
    }

    protected function generateTransactionReference(): string
    {
        return strtoupper($this->identifier()).'-'.now()->format('YmdHis').'-'.strtoupper(bin2hex(random_bytes(3)));
    }

    public function checkConfiguration(): ?ErrorResponse
    {
        if (! $this->isEnabled()) {
            return ErrorResponse::logged(ErrorCode::PaymentGatewayUnsupported);
        }

        if ($this->isSimulation()) {
            return null;
        }

        foreach ($this->requiredConfigKeys() as $key) {
            if (empty($this->config[$key])) {
                ErrorLogger::logBusiness(ErrorCode::PaymentGatewayMisconfigured, 'Gateway configuration is incomplete.', [
                    'gateway' => $this->identifier(),
                    'missing_key' => $key,
                ]);

                return ErrorResponse::logged(ErrorCode::PaymentGatewayUnsupported);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function checkPayload(array $payload): ?ErrorResponse
    {
        return $this->validatePayload($payload);
    }
}
