<?php

declare(strict_types=1);

namespace Modules\Payment\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Order\Database\Seeders\OrderDatabaseSeeder;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Models\Order;
use Modules\Payment\Enums\PaymentStatus;
use Modules\Payment\Models\Payment;

class PaymentDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (Order::query()->doesntExist()) {
            $this->call(OrderDatabaseSeeder::class);
        }

        DB::transaction(function (): void {
            Payment::query()->delete();

            $confirmed = Order::query()->where('status', OrderStatus::Confirmed)->orderBy('id')->get();

            $confirmed->take(2)->each(fn (Order $order) => Payment::factory()->create([
                'order_id' => $order->id,
                'user_id' => $order->user_id,
                'amount' => $order->total,
                'status' => PaymentStatus::Successful,
            ]));

            // Two failed attempts followed by a successful retry on the same order.
            $confirmed->slice(2, 2)->each(function (Order $order): void {
                Payment::factory()->count(2)->failed()->create([
                    'order_id' => $order->id,
                    'user_id' => $order->user_id,
                    'amount' => $order->total,
                    'payment_method' => 'credit_card',
                ]);

                Payment::factory()->create([
                    'order_id' => $order->id,
                    'user_id' => $order->user_id,
                    'amount' => $order->total,
                    'status' => PaymentStatus::Successful,
                    'payment_method' => 'paypal',
                ]);
            });

            Order::query()->where('status', OrderStatus::Cancelled)->orderBy('id')->get()
                ->each(fn (Order $order) => Payment::factory()->failed()->create([
                    'order_id' => $order->id,
                    'user_id' => $order->user_id,
                    'amount' => $order->total,
                ]));
        });

        $this->command->info(sprintf('Payment: seeded %d payments.', Payment::count()));
    }
}
