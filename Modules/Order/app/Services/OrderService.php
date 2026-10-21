<?php

declare(strict_types=1);

namespace Modules\Order\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Core\Enums\ErrorCode;
use Modules\Core\Exceptions\ApiBusinessException;
use Modules\Core\Support\PaginationMeta;
use Modules\Core\Support\VersionedCache;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Http\Resources\OrderResource;
use Modules\Order\Models\Order;

class OrderService
{
    public const DEFAULT_PER_PAGE = 15;

    public function __construct(
        private readonly OrderTotalCalculator $totals,
        private readonly VersionedCache $cache,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data): Order
    {
        return DB::transaction(function () use ($user, $data): Order {
            $order = Order::query()->create([
                'user_id' => $user->id,
                'customer_name' => $data['customer_name'],
                'customer_email' => $data['customer_email'],
                'customer_phone' => $data['customer_phone'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => OrderStatus::Pending,
                'total' => $this->totals->calculate($data['items']),
            ]);

            $this->replaceItems($order, $data['items']);

            return $order->load('items')->loadCount('payments');
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{data: array<int, mixed>, meta: array<string, mixed>}
     */
    public function list(User $user, array $filters, int $perPage): array
    {
        $tag = 'orders:user:'.$user->id;
        $key = md5(json_encode([$filters, $perPage]) ?: '');

        return $this->cache->remember($tag, $key, 60, function () use ($user, $filters, $perPage): array {
            $query = Order::query()
                ->where('user_id', $user->id)
                ->withCount('payments')
                ->createdBetween($filters['from'] ?? null, $filters['to'] ?? null)
                ->recent();

            if (! empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            $useCursor = ($filters['pagination'] ?? null) === 'cursor' || array_key_exists('cursor', $filters);
            $paginator = $useCursor
                ? $query->cursorPaginate($perPage, ['*'], 'cursor', $filters['cursor'] ?? null)
                : $query->paginate($perPage);

            return [
                'data' => OrderResource::collection($paginator->items())->resolve(),
                'meta' => PaginationMeta::from($paginator),
            ];
        });
    }

    public function show(User $user, int $orderId): Order
    {
        return $this->findOwned($user, $orderId)->load('items')->loadCount('payments');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, int $orderId, array $data): Order
    {
        return DB::transaction(function () use ($user, $orderId, $data): Order {
            $order = $this->findOwned($user, $orderId, lock: true);

            if ($order->status === OrderStatus::Cancelled) {
                throw new ApiBusinessException(
                    ErrorCode::OrderInvalidTransition,
                    'Cancelled orders cannot be modified.',
                );
            }

            if (array_key_exists('items', $data) && $order->status !== OrderStatus::Pending) {
                throw new ApiBusinessException(ErrorCode::OrderItemsLocked);
            }

            if (isset($data['status'])) {
                $target = OrderStatus::from((string) $data['status']);
                $this->assertTransition($order, $target);
                $order->status = $target;
            }

            foreach (['customer_name', 'customer_email', 'customer_phone', 'notes'] as $field) {
                if (array_key_exists($field, $data)) {
                    $order->{$field} = $data[$field];
                }
            }

            if (array_key_exists('items', $data)) {
                $this->replaceItems($order, $data['items']);
                $order->total = $this->totals->calculate($data['items']);
            }

            $order->save();

            return $order->load('items')->loadCount('payments');
        });
    }

    public function delete(User $user, int $orderId): void
    {
        DB::transaction(function () use ($user, $orderId): void {
            $order = $this->findOwned($user, $orderId, lock: true);

            if ($order->payments()->exists()) {
                throw new ApiBusinessException(ErrorCode::OrderDeleteHasPayments);
            }

            $order->items()->delete();
            $order->delete();
        });
    }

    private function assertTransition(Order $order, OrderStatus $target): void
    {
        if (! $order->status->canTransitionTo($target)) {
            throw new ApiBusinessException(ErrorCode::OrderInvalidTransition);
        }

        if ($order->status === OrderStatus::Confirmed
            && $target === OrderStatus::Cancelled
            && $order->hasSuccessfulPayment()) {
            throw new ApiBusinessException(
                ErrorCode::OrderInvalidTransition,
                'Cannot cancel an order that has a successful payment.',
            );
        }
    }

    /**
     * @param  list<array{product_name: string, quantity: int, price: int|float|string}>  $items
     */
    private function replaceItems(Order $order, array $items): void
    {
        $order->items()->delete();

        $rows = [];

        foreach ($items as $item) {
            $unitPrice = number_format((float) $item['price'], 2, '.', '');
            $rows[] = [
                'product_name' => $item['product_name'],
                'quantity' => (int) $item['quantity'],
                'unit_price' => $unitPrice,
                'line_total' => bcmul($unitPrice, (string) $item['quantity'], 2),
            ];
        }

        $order->items()->createMany($rows);
    }

    private function findOwned(User $user, int $orderId, bool $lock = false): Order
    {
        $query = Order::query()->whereKey($orderId);

        if ($lock) {
            $query->lockForUpdate();
        }

        $order = $query->first();

        if ($order === null) {
            throw new ApiBusinessException(ErrorCode::OrderNotFound);
        }

        if ($order->user_id !== $user->id) {
            throw new ApiBusinessException(ErrorCode::Forbidden);
        }

        return $order;
    }
}
