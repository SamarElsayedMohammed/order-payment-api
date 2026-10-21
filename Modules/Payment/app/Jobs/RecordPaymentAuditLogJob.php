<?php

declare(strict_types=1);

namespace Modules\Payment\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Modules\Payment\Models\Payment;
use Throwable;

class RecordPaymentAuditLogJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $paymentId)
    {
        $this->onQueue('payments');
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [5, 15, 30];
    }

    public function handle(): void
    {
        $payment = Payment::query()->find($this->paymentId);

        Log::channel('payments')->info('Payment audit recorded', [
            'payment_id' => $this->paymentId,
            'order_id' => $payment?->order_id,
            'status' => $payment?->status?->value,
            'transaction_id' => $payment?->transaction_id,
        ]);
    }

    public function failed(Throwable $exception): void
    {
        Log::channel('payments')->error('Payment audit job failed', [
            'payment_id' => $this->paymentId,
            'error' => $exception->getMessage(),
        ]);
    }
}
