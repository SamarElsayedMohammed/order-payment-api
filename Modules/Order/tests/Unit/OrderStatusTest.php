<?php

declare(strict_types=1);

namespace Modules\Order\Tests\Unit;

use Modules\Order\Enums\OrderStatus;
use PHPUnit\Framework\TestCase;

class OrderStatusTest extends TestCase
{
    public function test_pending_can_move_to_confirmed_or_cancelled(): void
    {
        $this->assertTrue(OrderStatus::Pending->canTransitionTo(OrderStatus::Confirmed));
        $this->assertTrue(OrderStatus::Pending->canTransitionTo(OrderStatus::Cancelled));
    }

    public function test_confirmed_can_only_move_to_cancelled(): void
    {
        $this->assertTrue(OrderStatus::Confirmed->canTransitionTo(OrderStatus::Cancelled));
        $this->assertFalse(OrderStatus::Confirmed->canTransitionTo(OrderStatus::Pending));
    }

    public function test_cancelled_is_final(): void
    {
        $this->assertFalse(OrderStatus::Cancelled->canTransitionTo(OrderStatus::Pending));
        $this->assertFalse(OrderStatus::Cancelled->canTransitionTo(OrderStatus::Confirmed));
        $this->assertTrue(OrderStatus::Cancelled->canTransitionTo(OrderStatus::Cancelled));
    }

    public function test_staying_in_the_same_status_is_allowed(): void
    {
        $this->assertTrue(OrderStatus::Pending->canTransitionTo(OrderStatus::Pending));
        $this->assertTrue(OrderStatus::Confirmed->canTransitionTo(OrderStatus::Confirmed));
    }
}
