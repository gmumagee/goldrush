<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remove the obsolete additions column so finalized sales rows only store the supported formula inputs.
        if (Schema::hasColumn('tbl_service_sales', 'inventory_additions')) {
            Schema::table('tbl_service_sales', function (Blueprint $table) {
                $table->dropColumn('inventory_additions');
            });
        }
    }

    public function down(): void
    {
        // Restore the column for rollback compatibility with older reconciliation data.
        if (! Schema::hasColumn('tbl_service_sales', 'inventory_additions')) {
            Schema::table('tbl_service_sales', function (Blueprint $table) {
                $table->integer('inventory_additions')
                    ->default(0)
                    ->after('opening_quantity');
            });
        }
    }
};
