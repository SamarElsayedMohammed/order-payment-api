<?php

declare(strict_types=1);

namespace Modules\Payment\Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Modules\Order\Models\Order;
use Modules\Payment\Jobs\RecordPaymentAuditLogJob;
use Modules\Payment\Jobs\SendPaymentNotificationJob;
use Modules\Payment\Models\Payment;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    public function test_payment_is_rejected_unless_the_order_is_confirmed(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->create();

        $this->asUser($user)->postJson('/api/v1/orders/'.$order->id.'/payments', [
            'payment_method' => 'credit_card',
        ])->assertStatus(409)->assertJsonPath('trace_code', 'PAY-PRC-002');
    }

    public function test_confirmed_order_can_be_paid_and_cannot_be_paid_or_deleted_again(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $order = Order::factory()->for($user)->confirmed()->create(['total' => '26.00']);

        $response = $this->asUser($user)->postJson('/api/v1/orders/'.$order->id.'/payments', [
            'payment_method' => 'paypal',
        ]);

        $response->assertCreated()
            ->assertJsonPath('trace_code', 'PAY-PRC-200')
            ->assertJsonPath('data.status', 'successful')
            ->assertJsonPath('data.amount', '26.00');

        Queue::assertPushed(SendPaymentNotificationJob::class);
        Queue::assertPushed(RecordPaymentAuditLogJob::class);

        $this->asUser($user)->postJson('/api/v1/orders/'.$order->id.'/payments', [
            'payment_method' => 'paypal',
        ])->assertStatus(409)->assertJsonPath('trace_code', 'PAY-PRC-005');

        $this->asUser($user)->deleteJson('/api/v1/orders/'.$order->id)
            ->assertStatus(409)
            ->assertJsonPath('trace_code', 'ORD-DEL-001');
    }

    public function test_failed_payment_is_stored_and_blocks_deletion(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->confirmed()->create();

        $this->asUser($user)->postJson('/api/v1/orders/'.$order->id.'/payments', [
            'payment_method' => 'credit_card',
            'simulate_outcome' => 'failed',
        ])->assertStatus(402)
            ->assertJsonPath('trace_code', 'PAY-PRC-004')
            ->assertJsonPath('data.status', 'failed');

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'status' => 'failed',
        ]);

        $this->asUser($user)->deleteJson('/api/v1/orders/'.$order->id)
            ->assertStatus(409)
            ->assertJsonPath('trace_code', 'ORD-DEL-001');
    }

    public function test_idempotency_key_replays_the_original_payment_and_rejects_a_different_payload(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->confirmed()->create();

        $first = $this->asUser($user)->postJson('/api/v1/orders/'.$order->id.'/payments', [
            'payment_method' => 'credit_card',
        ], ['Idempotency-Key' => 'key-123']);

        $first->assertCreated();
        $paymentId = $first->json('data.id');

        $this->asUser($user)->postJson('/api/v1/orders/'.$order->id.'/payments', [
            'payment_method' => 'credit_card',
        ], ['Idempotency-Key' => 'key-123'])
            ->assertOk()
            ->assertJsonPath('data.id', $paymentId);

        $this->assertSame(1, Payment::query()->where('order_id', $order->id)->count());

        $secondOrder = Order::factory()->for($user)->confirmed()->create();

        $this->asUser($user)->postJson('/api/v1/orders/'.$secondOrder->id.'/payments', [
            'payment_method' => 'paypal',
        ], ['Idempotency-Key' => 'key-123'])
            ->assertStatus(409)
            ->assertJsonPath('trace_code', 'PAY-PRC-006');
    }

    public function test_unknown_payment_method_is_rejected_by_validation(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->confirmed()->create();

        $this->asUser($user)->postJson('/api/v1/orders/'.$order->id.'/payments', [
            'payment_method' => 'stripe',
        ])->assertStatus(422)->assertJsonPath('trace_code', 'PAY-PRC-001');
    }

    public function test_disabled_gateway_is_rejected_without_storing_a_payment(): void
    {
        config()->set('payment.gateways.credit_card.enabled', false);

        $user = User::factory()->create();
        $order = Order::factory()->for($user)->confirmed()->create();

        $this->asUser($user)->postJson('/api/v1/orders/'.$order->id.'/payments', [
            'payment_method' => 'credit_card',
        ])->assertStatus(422)->assertJsonPath('trace_code', 'PAY-PRC-003');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_idempotency_keys_are_scoped_to_the_user(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $firstOrder = Order::factory()->for($first)->confirmed()->create();
        $secondOrder = Order::factory()->for($second)->confirmed()->create();

        $this->asUser($first)->postJson('/api/v1/orders/'.$firstOrder->id.'/payments', [
            'payment_method' => 'paypal',
        ], ['Idempotency-Key' => 'shared-key'])->assertCreated();

        $this->asUser($second)->postJson('/api/v1/orders/'.$secondOrder->id.'/payments', [
            'payment_method' => 'paypal',
        ], ['Idempotency-Key' => 'shared-key'])->assertCreated();

        $this->assertDatabaseCount('payments', 2);
    }

    public function test_invalid_idempotency_key_is_rejected(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->confirmed()->create();

        $this->asUser($user)->postJson('/api/v1/orders/'.$order->id.'/payments', [
            'payment_method' => 'paypal',
        ], ['Idempotency-Key' => str_repeat('a', 300)])
            ->assertStatus(422)
            ->assertJsonPath('trace_code', 'PAY-PRC-001');
    }

    public function test_other_users_cannot_view_or_pay_for_an_order(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $order = Order::factory()->for($owner)->confirmed()->create();

        $paymentId = $this->asUser($owner)->postJson('/api/v1/orders/'.$order->id.'/payments', [
            'payment_method' => 'paypal',
        ])->assertCreated()->json('data.id');

        $this->asUser($intruder)->getJson('/api/v1/payments/'.$paymentId)
            ->assertForbidden()
            ->assertJsonPath('trace_code', 'SYS-GEN-005');

        $this->asUser($intruder)->postJson('/api/v1/orders/'.$order->id.'/payments', [
            'payment_method' => 'paypal',
        ])->assertForbidden()->assertJsonPath('trace_code', 'SYS-GEN-005');

        $this->asUser($intruder)->getJson('/api/v1/orders/'.$order->id.'/payments')
            ->assertForbidden();
    }

    public function test_order_payments_list_respects_the_page(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->confirmed()->create();

        Payment::factory()->failed()->count(2)->create(['order_id' => $order->id, 'user_id' => $user->id]);

        $firstPage = $this->asUser($user)->getJson('/api/v1/orders/'.$order->id.'/payments?per_page=1')
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 2)
            ->json('data.0.id');

        $secondPage = $this->asUser($user)->getJson('/api/v1/orders/'.$order->id.'/payments?per_page=1&page=2')
            ->assertOk()
            ->json('data.0.id');

        $this->assertNotSame($firstPage, $secondPage);
    }

    public function test_paid_order_cannot_be_cancelled(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->confirmed()->create();

        $this->asUser($user)->postJson('/api/v1/orders/'.$order->id.'/payments', [
            'payment_method' => 'paypal',
        ])->assertCreated();

        $this->asUser($user)->patchJson('/api/v1/orders/'.$order->id, [
            'status' => 'cancelled',
        ])->assertStatus(409)->assertJsonPath('trace_code', 'ORD-UPD-003');
    }

    public function test_payments_can_be_filtered_by_method(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->confirmed()->create();

        $this->asUser($user)->postJson('/api/v1/orders/'.$order->id.'/payments', [
            'payment_method' => 'paypal',
        ])->assertCreated();

        $this->asUser($user)
            ->getJson('/api/v1/payments?method=paypal')
            ->assertOk()
            ->assertJsonPath('trace_code', 'PAY-LST-200')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.payment_method', 'paypal');

        $this->asUser($user)
            ->getJson('/api/v1/payments?method=credit_card')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
