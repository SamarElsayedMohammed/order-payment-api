<?php

declare(strict_types=1);

namespace Modules\Order\Services;

final class OrderTotalCalculator
{
    /**
     * @param  list<array{quantity: int|string, price: int|float|string}>  $items
     */
    public function calculate(array $items): string
    {
        $total = '0.00';

        foreach ($items as $item) {
            $line = bcmul($this->money($item['price']), (string) $item['quantity'], 2);
            $total = bcadd($total, $line, 2);
        }

        return $total;
    }

    private function money(int|float|string $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
