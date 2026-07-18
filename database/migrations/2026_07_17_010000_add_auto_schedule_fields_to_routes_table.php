<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tbl_routes', 'warehouse_id')) {
            Schema::table('tbl_routes', function (Blueprint $table) {
                $table->foreignId('warehouse_id')
                    ->nullable()
                    ->after('scheduled_day')
                    ->constrained('tbl_warehouses')
                    ->nullOnDelete()
                    ->cascadeOnUpdate();
            });
        }

        if (! Schema::hasColumn('tbl_routes', 'assigned_user_id')) {
            Schema::table('tbl_routes', function (Blueprint $table) {
                $table->foreignId('assigned_user_id')
                    ->nullable()
                    ->after('warehouse_id')
                    ->constrained('tbl_users')
                    ->nullOnDelete()
                    ->cascadeOnUpdate();
            });
        }

        if (! Schema::hasColumn('tbl_routes', 'auto_schedule_enabled')) {
            Schema::table('tbl_routes', function (Blueprint $table) {
                $table->boolean('auto_schedule_enabled')
                    ->default(true)
                    ->after('assigned_user_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tbl_routes', 'auto_schedule_enabled')) {
            Schema::table('tbl_routes', function (Blueprint $table) {
                $table->dropColumn('auto_schedule_enabled');
            });
        }

        if (Schema::hasColumn('tbl_routes', 'assigned_user_id')) {
            Schema::table('tbl_routes', function (Blueprint $table) {
                $table->dropConstrainedForeignId('assigned_user_id');
            });
        }

        if (Schema::hasColumn('tbl_routes', 'warehouse_id')) {
            Schema::table('tbl_routes', function (Blueprint $table) {
                $table->dropConstrainedForeignId('warehouse_id');
            });
        }
    }
};
