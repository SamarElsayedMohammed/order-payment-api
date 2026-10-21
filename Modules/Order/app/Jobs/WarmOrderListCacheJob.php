<?php

declare(strict_types=1);

namespace Modules\Order\Jobs;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Modules\Core\Enums\PerPage;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Services\OrderService;
use Throwable;

/**
 * Rebuilds the first page of a user's order list, unfiltered and per status, so the next read is a cache hit.
 */
class WarmOrderListCacheJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $userId)
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

    public function handle(OrderService $orders): void
    {
        $user = User::query()->find($this->userId);

        if ($user === null) {
            return;
        }

        $orders->list($user, [], PerPage::Default->value);

        foreach (OrderStatus::cases() as $status) {
            $orders->list($user, ['status' => $status->value], PerPage::Default->value);
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Order cache warm job failed', [
            'user_id' => $this->userId,
            'error' => $exception->getMessage(),
        ]);
    }
}
