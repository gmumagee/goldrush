<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Location;
use App\Models\Plan;
use App\Support\Demo;

class DemoAccountSeeder extends AbstractDemoSeeder
{
    public function run(): void
    {
        $account = Account::query()->updateOrCreate(
            ['slug' => Demo::accountSlug()],
            [
                'plan_id' => Plan::PRO_ID,
                'account_name' => 'GoldRush Public Demo',
                'status' => Account::STATUS_ACTIVE,
                'billing_email' => 'demo@goldrush.example',
                'phone' => '555-100-0000',
            ],
        );

        Location::ensureInventoryLocationForAccount($account->id);
    }
}
