<?php

declare(strict_types=1);

namespace Modules\Payment\Observers;

use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Modules\Core\Support\VersionedCache;
use Modules\Payment\Models\Payment;

class PaymentObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(private readonly VersionedCache $cache) {}

    public function saved(Payment $payment): void
    {
        $this->cache->flush('payments:user:'.$payment->user_id);
        $this->cache->flush('orders:user:'.$payment->user_id);
    }
}
