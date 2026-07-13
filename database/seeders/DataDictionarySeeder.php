<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DataDictionarySeeder extends Seeder
{
    public function run(): void
    {
        // Re-seed the shared lookup values so forms and workflow validation
        // read their canonical status options from one database source.
        $this->seedStatuses();
        $this->seedSupportingValues();
    }

    protected function seedStatuses(): void
    {
        $statusGroups = [
            'service_status',
            'purchase_status',
            'machine_status',
            'account_status',
            'user_status',
            'account_user_status',
            'inventory_movement_type',
        ];

        DB::table('tbl_data_dictionary')
            ->whereIn('name', $statusGroups)
            ->delete();

        $timestamp = now();

        DB::table('tbl_data_dictionary')->insert([
            ['account_id' => null, 'name' => 'service_status', 'value' => 'Awaiting Service', 'label' => 'Awaiting Service', 'sort_order' => 10, 'is_active' => true, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['account_id' => null, 'name' => 'service_status', 'value' => 'Service Open', 'label' => 'Service Open', 'sort_order' => 20, 'is_active' => true, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['account_id' => null, 'name' => 'service_status', 'value' => 'Service Completed', 'label' => 'Service Completed', 'sort_order' => 30, 'is_active' => true, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['account_id' => null, 'name' => 'service_status', 'value' => 'Service Closed', 'label' => 'Service Closed', 'sort_order' => 40, 'is_active' => true, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['account_id' => null, 'name' => 'purchase_status', 'value' => 'Posted', 'label' => 'Posted', 'sort_order' => 10, 'is_active' => true, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['account_id' => null, 'name' => 'purchase_status', 'value' => 'Voided', 'label' => 'Voided', 'sort_order' => 20, 'is_active' => true, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['account_id' => null, 'name' => 'machine_status', 'value' => 'active', 'label' => 'Active', 'sort_order' => 10, 'is_active' => true, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['account_id' => null, 'name' => 'machine_status', 'value' => 'inactive', 'label' => 'Inactive', 'sort_order' => 20, 'is_active' => true, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['account_id' => null, 'name' => 'machine_status', 'value' => 'repair', 'label' => 'Repair', 'sort_order' => 30, 'is_active' => true, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['account_id' => null, 'name' => 'machine_status', 'value' => 'retired', 'label' => 'Retired', 'sort_order' => 40, 'is_active' => true, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['account_id' => null, 'name' => 'account_status', 'value' => 'active', 'label' => 'Active', 'sort_order' => 10, 'is_active' => true, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['account_id' => null, 'name' => 'account_status', 'value' => 'inactive', 'label' => 'Inactive', 'sort_order' => 20, 'is_active' => true, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['account_id' => null, 'name' => 'user_status', 'value' => 'active', 'label' => 'Active', 'sort_order' => 10, 'is_active' => true, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['account_id' => null, 'name' => 'user_status', 'value' => 'inactive', 'label' => 'Inactive', 'sort_order' => 20, 'is_active' => true, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['account_id' => null, 'name' => 'account_user_status', 'value' => 'active', 'label' => 'Active', 'sort_order' => 10, 'is_active' => true, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['account_id' => null, 'name' => 'account_user_status', 'value' => 'inactive', 'label' => 'Inactive', 'sort_order' => 20, 'is_active' => true, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['account_id' => null, 'name' => 'inventory_movement_type', 'value' => 'purchase', 'label' => 'Purchase', 'sort_order' => 10, 'is_active' => true, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['account_id' => null, 'name' => 'inventory_movement_type', 'value' => 'purchase_void', 'label' => 'Purchase Void', 'sort_order' => 20, 'is_active' => true, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['account_id' => null, 'name' => 'inventory_movement_type', 'value' => 'service_fill', 'label' => 'Service Fill', 'sort_order' => 30, 'is_active' => true, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['account_id' => null, 'name' => 'inventory_movement_type', 'value' => 'adjustment', 'label' => 'Adjustment', 'sort_order' => 40, 'is_active' => true, 'created_at' => $timestamp, 'updated_at' => $timestamp],
        ]);
    }

    protected function seedSupportingValues(): void
    {
        $timestamp = now();

        DB::table('tbl_data_dictionary')
            ->whereIn('name', ['machine_type', 'service_type', 'account_user_role', 'route_scheduled_day'])
            ->delete();

        DB::table('tbl_data_dictionary')->insert([
            ['account_id' => null, 'name' => 'machine_type', 'value' => 'Soda', 'label' => 'Soda', 'sort_order' => 10, 'is_active' => true, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['account_id' => null, 'name' => 'machine_type', 'value' => 'Snack', 'label' => 'Snack', 'sort_order' => 20, 'is_active' => true, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['account_id' => null, 'name' => 'service_type', 'value' => 'location_service', 'label' => 'Location Service', 'sort_order' => 10, 'is_active' => true, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['account_id' => null, 'name' => 'account_user_role', 'value' => 'Owner', 'label' => 'Owner', 'sort_order' => 10, 'is_active' => true, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['account_id' => null, 'name' => 'account_user_role', 'value' => 'Admin', 'label' => 'Admin', 'sort_order' => 20, 'is_active' => true, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['account_id' => null, 'name' => 'account_user_role', 'value' => 'Manager', 'label' => 'Manager', 'sort_order' => 30, 'is_active' => true, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['account_id' => null, 'name' => 'account_user_role', 'value' => 'Technician', 'label' => 'Technician', 'sort_order' => 40, 'is_active' => true, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['account_id' => null, 'name' => 'account_user_role', 'value' => 'Viewer', 'label' => 'Viewer', 'sort_order' => 50, 'is_active' => true, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['account_id' => null, 'name' => 'route_scheduled_day', 'value' => 'Monday', 'label' => 'Monday', 'sort_order' => 10, 'is_active' => true, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['account_id' => null, 'name' => 'route_scheduled_day', 'value' => 'Tuesday', 'label' => 'Tuesday', 'sort_order' => 20, 'is_active' => true, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['account_id' => null, 'name' => 'route_scheduled_day', 'value' => 'Wednesday', 'label' => 'Wednesday', 'sort_order' => 30, 'is_active' => true, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['account_id' => null, 'name' => 'route_scheduled_day', 'value' => 'Thursday', 'label' => 'Thursday', 'sort_order' => 40, 'is_active' => true, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['account_id' => null, 'name' => 'route_scheduled_day', 'value' => 'Friday', 'label' => 'Friday', 'sort_order' => 50, 'is_active' => true, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['account_id' => null, 'name' => 'route_scheduled_day', 'value' => 'Saturday', 'label' => 'Saturday', 'sort_order' => 60, 'is_active' => true, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['account_id' => null, 'name' => 'route_scheduled_day', 'value' => 'Sunday', 'label' => 'Sunday', 'sort_order' => 70, 'is_active' => true, 'created_at' => $timestamp, 'updated_at' => $timestamp],
        ]);
    }
}
