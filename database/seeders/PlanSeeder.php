<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        Plan::query()->upsert([
            [
                'id' => Plan::FREE_ID,
                'name' => 'Free',
                'slug' => Plan::FREE_SLUG,
                'machine_limit' => 10,
                'display_price' => '$0/mo',
                'sort_order' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => Plan::STARTER_ID,
                'name' => 'Starter',
                'slug' => Plan::STARTER_SLUG,
                'machine_limit' => 25,
                'display_price' => '$99/mo',
                'sort_order' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => Plan::PRO_ID,
                'name' => 'Pro',
                'slug' => Plan::PRO_SLUG,
                'machine_limit' => null,
                'display_price' => '$249/mo',
                'sort_order' => 3,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], ['id'], ['name', 'slug', 'machine_limit', 'display_price', 'sort_order', 'updated_at']);
    }
}
