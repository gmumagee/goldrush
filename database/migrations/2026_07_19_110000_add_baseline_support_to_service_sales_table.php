<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Mark baseline rows explicitly so first-service setup lines do not get reported as revenue.
        Schema::table('tbl_service_sales', function (Blueprint $table) {
            $table->string('calculation_status', 30)
                ->default('calculated')
                ->after('count_transaction_id');

            $table->text('calculation_note')
                ->nullable()
                ->after('calculation_status');
        });

        // Allow baseline rows to persist without inventing opening inventory or sales amounts.
        Schema::table('tbl_service_sales', function (Blueprint $table) {
            $table->unsignedBigInteger('previous_inventory_transaction_id')
                ->nullable()
                ->change();

            $table->integer('opening_quantity')
                ->nullable()
                ->change();

            $table->integer('units_sold')
                ->nullable()
                ->change();

            $table->decimal('sales_amount', 14, 2)
                ->nullable()
                ->change();
        });
    }

    public function down(): void
    {
        // Restore the pre-baseline schema if this migration is rolled back.
        Schema::table('tbl_service_sales', function (Blueprint $table) {
            $table->unsignedBigInteger('previous_inventory_transaction_id')
                ->nullable(false)
                ->change();

            $table->integer('opening_quantity')
                ->nullable(false)
                ->change();

            $table->integer('units_sold')
                ->nullable(false)
                ->change();

            $table->decimal('sales_amount', 14, 2)
                ->nullable(false)
                ->change();

            $table->dropColumn([
                'calculation_status',
                'calculation_note',
            ]);
        });
    }
};
