<?php

declare(strict_types=1);

namespace Modules\Payment\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Modules\Payment\Models\Payment;
use Throwable;

class SendPaymentNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $paymentId)
    {
        $this->onQueue('notifications');
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

        Log::info('Payment notification dispatched', [
            'payment_id' => $this->paymentId,
            'status' => $payment?->status?->value,
        ]);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Payment notification job failed', [
            'payment_id' => $this->paymentId,
            'error' => $exception->getMessage(),
        ]);
    }
}
