<?php

declare(strict_types=1);

namespace Modules\Order\Observers;

use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Modules\Core\Support\VersionedCache;
use Modules\Order\Jobs\WarmOrderListCacheJob;
use Modules\Order\Models\Order;

class OrderObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(private readonly VersionedCache $cache) {}

    public function saved(Order $order): void
    {
        $this->cache->flush($this->tag($order));
        WarmOrderListCacheJob::dispatch($order->user_id);
    }

    public function deleted(Order $order): void
    {
        $this->cache->flush($this->tag($order));
    }

    private function tag(Order $order): string
    {
        return 'orders:user:'.$order->user_id;
    }
}
