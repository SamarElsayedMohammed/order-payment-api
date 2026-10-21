<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Auth\Database\Seeders\AuthDatabaseSeeder;
use Modules\Order\Database\Seeders\OrderDatabaseSeeder;
use Modules\Payment\Database\Seeders\PaymentDatabaseSeeder;

/**
 * Seeds each module in dependency order. Auth creates the users that Order
 * attaches orders to, and Payment attaches payments to those orders.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AuthDatabaseSeeder::class,
            OrderDatabaseSeeder::class,
            PaymentDatabaseSeeder::class,
        ]);
    }
}
