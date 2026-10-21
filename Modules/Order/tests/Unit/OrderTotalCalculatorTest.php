<?php

declare(strict_types=1);

namespace Modules\Order\Tests\Unit;

use Modules\Order\Services\OrderTotalCalculator;
use PHPUnit\Framework\TestCase;

class OrderTotalCalculatorTest extends TestCase
{
    public function test_it_calculates_line_totals_and_ignores_floating_point_drift(): void
    {
        $total = (new OrderTotalCalculator)->calculate([
            ['quantity' => 2, 'price' => '10.10'],
            ['quantity' => 1, 'price' => '0.20'],
        ]);

        $this->assertSame('20.40', $total);
    }

    public function test_it_returns_zero_for_empty_items(): void
    {
        $this->assertSame('0.00', (new OrderTotalCalculator)->calculate([]));
    }

    public function test_it_handles_the_largest_allowed_order_without_precision_loss(): void
    {
        $items = array_fill(0, 100, ['quantity' => 10000, 'price' => '999999.99']);

        $this->assertSame('999999990000.00', (new OrderTotalCalculator)->calculate($items));
    }

    public function test_it_accepts_numeric_prices_as_int_float_or_string(): void
    {
        $total = (new OrderTotalCalculator)->calculate([
            ['quantity' => 1, 'price' => 5],
            ['quantity' => 3, 'price' => 0.1],
            ['quantity' => 2, 'price' => '1.50'],
        ]);

        $this->assertSame('8.30', $total);
    }
}
