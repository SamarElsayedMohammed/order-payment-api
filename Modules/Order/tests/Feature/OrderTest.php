<?php

declare(strict_types=1);

namespace Modules\Order\Tests\Feature;

use App\Models\User;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderItem;
use Tests\TestCase;

class OrderTest extends TestCase
{
    public function test_order_total_is_calculated_on_the_server(): void
    {
        $user = User::factory()->create();

        $response = $this->asUser($user)->postJson('/api/v1/orders', [
            'customer_name' => 'Mona Ali',
            'customer_email' => 'mona@example.com',
            'total' => '1.00',
            'items' => [
                ['product_name' => 'Notebook', 'quantity' => 2, 'price' => 10.5],
                ['product_name' => 'Pen', 'quantity' => 1, 'price' => 5],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('trace_code', 'ORD-CRT-200')
            ->assertJsonPath('data.total', '26.00')
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_orders_can_be_filtered_by_status_and_are_private_to_the_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $pending = Order::factory()->for($owner)->create(['status' => 'pending', 'total' => '10.00']);
        Order::factory()->for($owner)->confirmed()->create(['total' => '12.00']);
        $foreign = Order::factory()->for($other)->create();

        $this->asUser($owner)
            ->getJson('/api/v1/orders?status=pending')
            ->assertOk()
            ->assertJsonPath('trace_code', 'ORD-LST-200')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $pending->id);

        $this->asUser($owner)
            ->getJson('/api/v1/orders/'.$foreign->id)
            ->assertForbidden()
            ->assertJsonPath('trace_code', 'SYS-GEN-005');
    }

    public function test_items_cannot_be_edited_after_confirmation_and_cancelled_is_final(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->confirmed()->create();

        $this->asUser($user)->patchJson('/api/v1/orders/'.$order->id, [
            'items' => [
                ['product_name' => 'Notebook', 'quantity' => 1, 'price' => 10],
            ],
        ])->assertStatus(409)->assertJsonPath('trace_code', 'ORD-UPD-002');

        $cancelled = Order::factory()->for($user)->cancelled()->create();

        $this->asUser($user)->patchJson('/api/v1/orders/'.$cancelled->id, [
            'status' => 'confirmed',
        ])->assertStatus(409)->assertJsonPath('trace_code', 'ORD-UPD-003');
    }

    public function test_order_can_be_confirmed_and_deleted_when_it_has_no_payments(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->create();

        $this->asUser($user)->patchJson('/api/v1/orders/'.$order->id, [
            'status' => 'confirmed',
        ])->assertOk()->assertJsonPath('data.status', 'confirmed')->assertJsonPath('trace_code', 'ORD-UPD-200');

        $this->asUser($user)->deleteJson('/api/v1/orders/'.$order->id)
            ->assertOk()
            ->assertJsonPath('trace_code', 'ORD-DEL-200');

        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
    }

    public function test_list_paginates_and_leaves_item_details_for_the_show_endpoint(): void
    {
        $user = User::factory()->create();
        $first = Order::factory()->for($user)->create();
        OrderItem::factory()->for($first)->create(['product_name' => 'Notebook']);
        Order::factory()->for($user)->create();

        $this->asUser($user)
            ->getJson('/api/v1/orders?per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.pagination.mode', 'offset')
            ->assertJsonPath('meta.pagination.total', 2)
            ->assertJsonPath('meta.pagination.per_page', 1)
            ->assertJsonMissingPath('data.0.items');

        $cursor = $this->asUser($user)
            ->getJson('/api/v1/orders?pagination=cursor&per_page=1')
            ->assertOk()
            ->assertJsonPath('meta.pagination.mode', 'cursor')
            ->assertJsonPath('meta.pagination.has_more', true)
            ->json('meta.pagination.next_cursor');

        $this->assertNotEmpty($cursor);

        $this->asUser($user)
            ->getJson('/api/v1/orders/'.$first->id)
            ->assertOk()
            ->assertJsonPath('data.items.0.product_name', 'Notebook');
    }

    public function test_create_order_validation_returns_an_indexed_error_code(): void
    {
        $user = User::factory()->create();

        $this->asUser($user)->postJson('/api/v1/orders', [])
            ->assertStatus(422)
            ->assertJsonPath('trace_code', 'ORD-CRT-001')
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['customer_name', 'customer_email', 'items']]);

        $this->asUser($user)->postJson('/api/v1/orders', [
            'customer_name' => 'Mona Ali',
            'customer_email' => 'mona@example.com',
            'items' => [
                ['product_name' => 'Notebook', 'quantity' => 0, 'price' => -5],
            ],
        ])->assertStatus(422)
            ->assertJsonPath('trace_code', 'ORD-CRT-001')
            ->assertJsonStructure(['errors' => ['items.0.quantity', 'items.0.price']]);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_missing_order_returns_not_found_for_every_action(): void
    {
        $user = User::factory()->create();

        $this->asUser($user)->getJson('/api/v1/orders/999999')
            ->assertNotFound()
            ->assertJsonPath('trace_code', 'ORD-SHW-001');

        $this->asUser($user)->patchJson('/api/v1/orders/999999', ['notes' => 'x'])
            ->assertNotFound()
            ->assertJsonPath('trace_code', 'ORD-SHW-001');

        $this->asUser($user)->deleteJson('/api/v1/orders/999999')
            ->assertNotFound()
            ->assertJsonPath('trace_code', 'ORD-SHW-001');
    }

    public function test_other_users_cannot_update_or_delete_an_order(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $order = Order::factory()->for($owner)->create(['customer_name' => 'Original']);

        $this->asUser($intruder)->patchJson('/api/v1/orders/'.$order->id, ['customer_name' => 'Hacked'])
            ->assertForbidden()
            ->assertJsonPath('trace_code', 'SYS-GEN-005');

        $this->asUser($intruder)->deleteJson('/api/v1/orders/'.$order->id)
            ->assertForbidden()
            ->assertJsonPath('trace_code', 'SYS-GEN-005');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'customer_name' => 'Original']);
    }

    public function test_pending_order_details_and_items_can_be_updated_and_total_is_recalculated(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->create(['total' => '10.00']);
        OrderItem::factory()->for($order)->create();

        $this->asUser($user)->patchJson('/api/v1/orders/'.$order->id, [
            'customer_name' => 'New Name',
            'total' => '1.00',
            'items' => [
                ['product_name' => 'Bag', 'quantity' => 3, 'price' => 7.25],
            ],
        ])->assertOk()
            ->assertJsonPath('trace_code', 'ORD-UPD-200')
            ->assertJsonPath('data.customer_name', 'New Name')
            ->assertJsonPath('data.total', '21.75')
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.product_name', 'Bag');

        $this->assertSame(1, OrderItem::query()->where('order_id', $order->id)->count());
    }

    public function test_pending_and_unpaid_confirmed_orders_can_be_cancelled(): void
    {
        $user = User::factory()->create();
        $pending = Order::factory()->for($user)->create();
        $confirmed = Order::factory()->for($user)->confirmed()->create();

        $this->asUser($user)->patchJson('/api/v1/orders/'.$pending->id, ['status' => 'cancelled'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->asUser($user)->patchJson('/api/v1/orders/'.$confirmed->id, ['status' => 'cancelled'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_confirmed_order_cannot_go_back_to_pending(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->confirmed()->create();

        $this->asUser($user)->patchJson('/api/v1/orders/'.$order->id, ['status' => 'pending'])
            ->assertStatus(409)
            ->assertJsonPath('trace_code', 'ORD-UPD-003');
    }

    public function test_invalid_update_values_and_list_filters_are_rejected(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->create();

        $this->asUser($user)->patchJson('/api/v1/orders/'.$order->id, ['status' => 'shipped'])
            ->assertStatus(422)
            ->assertJsonPath('trace_code', 'ORD-UPD-001');

        $this->asUser($user)->getJson('/api/v1/orders?status=shipped')
            ->assertStatus(422)
            ->assertJsonPath('trace_code', 'ORD-LST-001');

        $this->asUser($user)->getJson('/api/v1/orders?from=2026-10-10&to=2026-10-01')
            ->assertStatus(422)
            ->assertJsonPath('trace_code', 'ORD-LST-001');
    }

    public function test_orders_can_be_filtered_by_date_range(): void
    {
        $user = User::factory()->create();
        $old = Order::factory()->for($user)->create(['created_at' => now()->subDays(10)]);
        $recent = Order::factory()->for($user)->create(['created_at' => now()->subDay()]);

        $this->asUser($user)
            ->getJson('/api/v1/orders?from='.now()->subDays(3)->toDateString())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $recent->id);

        $this->asUser($user)
            ->getJson('/api/v1/orders?to='.now()->subDays(5)->toDateString())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $old->id);
    }

    public function test_cached_order_list_is_refreshed_after_a_new_order(): void
    {
        $user = User::factory()->create();
        Order::factory()->for($user)->create();

        $this->asUser($user)->getJson('/api/v1/orders')->assertJsonCount(1, 'data');

        $this->asUser($user)->postJson('/api/v1/orders', [
            'customer_name' => 'Mona Ali',
            'customer_email' => 'mona@example.com',
            'items' => [
                ['product_name' => 'Pen', 'quantity' => 1, 'price' => 5],
            ],
        ])->assertCreated();

        $this->asUser($user)->getJson('/api/v1/orders')->assertJsonCount(2, 'data');
    }

    public function test_list_uses_the_default_page_size_when_none_is_sent(): void
    {
        $user = User::factory()->create();
        Order::factory()->for($user)->count(12)->create();

        $this->asUser($user)->getJson('/api/v1/orders')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.pagination.per_page', 10)
            ->assertJsonPath('meta.pagination.total', 12);

        $this->asUser($user)->getJson('/api/v1/orders?per_page=101')
            ->assertStatus(422)
            ->assertJsonPath('trace_code', 'ORD-LST-001');
    }

    public function test_order_routes_require_a_token(): void
    {
        $this->getJson('/api/v1/orders')
            ->assertUnauthorized()
            ->assertJsonPath('trace_code', 'AUTH-TKN-001');

        $this->postJson('/api/v1/orders', [])
            ->assertUnauthorized()
            ->assertJsonPath('trace_code', 'AUTH-TKN-001');
    }
}
