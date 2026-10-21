<?php

declare(strict_types=1);

namespace Modules\Payment\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Order\Models\Order;
use Modules\Payment\Enums\PaymentStatus;
use Modules\Payment\Models\Payment;

/** @extends Factory<Payment> */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'user_id' => User::factory(),
            'payment_method' => 'credit_card',
            'status' => PaymentStatus::Successful,
            'amount' => '10.00',
            'transaction_id' => 'CC-TEST',
            'idempotency_key' => null,
            'payload_hash' => null,
            'failure_reason' => null,
            'gateway_response' => ['id' => 'CC-TEST'],
        ];
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => PaymentStatus::Failed,
            'failure_reason' => 'Card declined',
        ]);
    }
}
