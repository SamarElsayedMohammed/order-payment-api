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
}
