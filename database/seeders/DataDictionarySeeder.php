<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DataDictionarySeeder extends Seeder
{
    public function run(): void
    {
        // Seed the first shared lookup values that are already hard-coded or
        // implied by the current application workflows.
        DB::table('tbl_data_dictionary')
            ->where('name', 'service_status')
            ->whereIn('value', ['open', 'completed', 'cancelled'])
            ->delete();

        DB::table('tbl_data_dictionary')->insertOrIgnore([
            ['name' => 'machine_type', 'value' => 'Soda'],
            ['name' => 'machine_type', 'value' => 'Snack'],
            ['name' => 'machine_status', 'value' => 'active'],
            ['name' => 'machine_status', 'value' => 'inactive'],
            ['name' => 'machine_status', 'value' => 'maintenance'],
            ['name' => 'service_status', 'value' => 'Awaiting Service'],
            ['name' => 'service_status', 'value' => 'Service Open'],
            ['name' => 'service_status', 'value' => 'Service Closed'],
            ['name' => 'service_type', 'value' => 'location_service'],
            ['name' => 'account_status', 'value' => 'active'],
            ['name' => 'user_status', 'value' => 'active'],
            ['name' => 'account_user_status', 'value' => 'active'],
            ['name' => 'account_user_role', 'value' => 'viewer'],
        ]);
    }
}
