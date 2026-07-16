<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $timestamp = now();
        $group = 'location_document_type';

        $rows = [
            ['account_id' => null, 'name' => $group, 'value' => 'Contract', 'label' => 'Contract', 'sort_order' => 10, 'is_active' => true, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['account_id' => null, 'name' => $group, 'value' => 'Insurance', 'label' => 'Insurance', 'sort_order' => 20, 'is_active' => true, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['account_id' => null, 'name' => $group, 'value' => 'Access Instructions', 'label' => 'Access Instructions', 'sort_order' => 30, 'is_active' => true, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['account_id' => null, 'name' => $group, 'value' => 'Agreement', 'label' => 'Agreement', 'sort_order' => 40, 'is_active' => true, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['account_id' => null, 'name' => $group, 'value' => 'Photo', 'label' => 'Photo', 'sort_order' => 50, 'is_active' => true, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['account_id' => null, 'name' => $group, 'value' => 'Other', 'label' => 'Other', 'sort_order' => 60, 'is_active' => true, 'created_at' => $timestamp, 'updated_at' => $timestamp],
        ];

        foreach ($rows as $row) {
            DB::table('tbl_data_dictionary')->updateOrInsert(
                [
                    'account_id' => $row['account_id'],
                    'name' => $row['name'],
                    'value' => $row['value'],
                ],
                [
                    'label' => $row['label'],
                    'sort_order' => $row['sort_order'],
                    'is_active' => $row['is_active'],
                    'created_at' => $row['created_at'],
                    'updated_at' => $row['updated_at'],
                ]
            );
        }
    }

    public function down(): void
    {
        $group = 'location_document_type';

        DB::table('tbl_data_dictionary')
            ->whereNull('account_id')
            ->where('name', $group)
            ->delete();
    }
};
