<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_service_sales', function (Blueprint $table) {
            $table->id();

            $table->foreignId('account_id')
                ->constrained('tbl_accounts')
                ->cascadeOnDelete();

            $table->foreignId('service_id')
                ->constrained('tbl_services')
                ->restrictOnDelete();

            $table->foreignId('location_id')
                ->constrained('tbl_locations')
                ->restrictOnDelete();

            $table->foreignId('machine_id')
                ->constrained('tbl_machines')
                ->restrictOnDelete();

            $table->foreignId('bin_id')
                ->constrained('tbl_bins')
                ->restrictOnDelete();

            $table->foreignId('product_id')
                ->constrained('tbl_products')
                ->restrictOnDelete();

            $table->foreignId('previous_inventory_transaction_id')
                ->constrained('tbl_transactions')
                ->restrictOnDelete();

            $table->foreignId('count_transaction_id')
                ->constrained('tbl_transactions')
                ->restrictOnDelete();

            $table->date('sales_date');
            $table->integer('opening_quantity');
            $table->integer('inventory_additions')->default(0);
            $table->integer('non_sale_removals')->default(0);
            $table->integer('counted_quantity');
            $table->integer('units_sold');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('sales_amount', 14, 2);
            $table->string('calculation_version', 50)->default('inventory_reconciliation_v1');
            $table->timestamp('calculated_at');
            $table->timestamps();

            $table->unique(
                ['account_id', 'service_id', 'bin_id', 'product_id'],
                'service_sales_service_bin_product_unique'
            );

            $table->unique(
                ['account_id', 'count_transaction_id'],
                'service_sales_count_transaction_unique'
            );

            $table->index(['account_id', 'sales_date'], 'service_sales_account_date_index');
            $table->index(['account_id', 'product_id', 'sales_date'], 'service_sales_product_date_index');
            $table->index(['account_id', 'machine_id', 'sales_date'], 'service_sales_machine_date_index');
            $table->index(['account_id', 'location_id', 'sales_date'], 'service_sales_location_date_index');
            $table->index(['account_id', 'service_id'], 'service_sales_service_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_service_sales');
    }
};
