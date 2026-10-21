<?php

declare(strict_types=1);

namespace Modules\Order\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Auth\Database\Seeders\AuthDatabaseSeeder;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderItem;
use Modules\Payment\Models\Payment;

class OrderDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::query()->orderBy('id')->get();

        if ($users->count() < AuthDatabaseSeeder::RECORDS) {
            $this->call(AuthDatabaseSeeder::class);
            $users = User::query()->orderBy('id')->get();
        }

        DB::transaction(function () use ($users): void {
            Payment::query()->delete();
            OrderItem::query()->delete();
            Order::query()->delete();

            $this->ordersFor($users)->each(function (Order $order): void {
                $item = OrderItem::factory()->create([
                    'order_id' => $order->id,
                    'quantity' => 2,
                    'unit_price' => '15.50',
                    'line_total' => '31.00',
                ]);

                $order->update(['total' => $item->line_total]);
            });
        });

        $this->command->info(sprintf(
            'Order: seeded %d orders and %d items.',
            Order::count(),
            OrderItem::count(),
        ));
    }

    /**
     * Four pending, four confirmed and two cancelled orders, one per user.
     *
     * @param  Collection<int, User>  $users
     * @return Collection<int, Order>
     */
    private function ordersFor(Collection $users): Collection
    {
        return $users->take(AuthDatabaseSeeder::RECORDS)->values()
            ->map(fn (User $user, int $index): Order => Order::factory()->create([
                'user_id' => $user->id,
                'status' => match (true) {
                    $index < 4 => OrderStatus::Pending,
                    $index < 8 => OrderStatus::Confirmed,
                    default => OrderStatus::Cancelled,
                },
                'total' => '0.00',
            ]));
    }
}
