<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Replace the generic removal snapshot with an explicit spoilage snapshot for service-sales reconciliation.
        if (! Schema::hasColumn('tbl_service_sales', 'spoilage')) {
            Schema::table('tbl_service_sales', function (Blueprint $table) {
                $table->unsignedInteger('spoilage')
                    ->default(0)
                    ->after('opening_quantity');
            });
        }

        if (Schema::hasColumn('tbl_service_sales', 'non_sale_removals')) {
            DB::table('tbl_service_sales')->update([
                'spoilage' => DB::raw('COALESCE(non_sale_removals, 0)'),
            ]);

            Schema::table('tbl_service_sales', function (Blueprint $table) {
                $table->dropColumn('non_sale_removals');
            });
        }
    }

    public function down(): void
    {
        // Restore the legacy removal field if the explicit spoilage snapshot is rolled back.
        if (! Schema::hasColumn('tbl_service_sales', 'non_sale_removals')) {
            Schema::table('tbl_service_sales', function (Blueprint $table) {
                $table->integer('non_sale_removals')
                    ->default(0)
                    ->after('opening_quantity');
            });
        }

        if (Schema::hasColumn('tbl_service_sales', 'spoilage')) {
            DB::table('tbl_service_sales')->update([
                'non_sale_removals' => DB::raw('COALESCE(spoilage, 0)'),
            ]);

            Schema::table('tbl_service_sales', function (Blueprint $table) {
                $table->dropColumn('spoilage');
            });
        }
    }
};
