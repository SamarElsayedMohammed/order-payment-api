<?php

declare(strict_types=1);

namespace Modules\Auth\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderItem;
use Modules\Payment\Models\Payment;

class AuthDatabaseSeeder extends Seeder
{
    public const RECORDS = 10;

    public const DEMO_EMAIL = 'demo@example.com';

    public const DEMO_PASSWORD = 'password';

    public function run(): void
    {
        DB::transaction(function (): void {
            Payment::query()->delete();
            OrderItem::query()->delete();
            Order::query()->delete();
            User::query()->delete();

            $users = User::factory()->count(self::RECORDS)->create([
                'password' => self::DEMO_PASSWORD,
            ]);

            $users->first()->update([
                'name' => 'Demo User',
                'email' => self::DEMO_EMAIL,
            ]);
        });

        $this->command->info(sprintf(
            'Auth: seeded %d users. Login with %s / %s.',
            User::count(),
            self::DEMO_EMAIL,
            self::DEMO_PASSWORD,
        ));
    }
}
