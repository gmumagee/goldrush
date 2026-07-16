<?php

namespace Database\Seeders;

use App\Models\Account;

class DemoAccountSeeder extends DemoSeeder
{
    public function run(): void
    {
        Account::query()->updateOrCreate(
            ['slug' => self::DEMO_ACCOUNT_SLUG],
            [
                'account_name' => 'Demo Vending Company',
                'status' => Account::STATUS_ACTIVE,
                'billing_email' => 'admin@example.com',
                'phone' => '555-100-0000',
            ],
        );

        Account::query()->updateOrCreate(
            ['slug' => self::OTHER_ACCOUNT_SLUG],
            [
                'account_name' => 'Other Vending Company',
                'status' => Account::STATUS_ACTIVE,
                'billing_email' => 'owner@other.test',
                'phone' => '555-200-0000',
            ],
        );
    }
}
