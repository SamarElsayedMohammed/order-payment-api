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
}
