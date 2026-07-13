<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_purchases', function (Blueprint $table) {
            $table->id();

            $table->foreignId('account_id')
                ->constrained('tbl_accounts')
                ->cascadeOnDelete();

            $table->foreignId('vendor_id')
                ->nullable()
                ->constrained('tbl_vendors')
                ->nullOnDelete();

            $table->foreignId('warehouse_id')
                ->constrained('tbl_warehouses')
                ->restrictOnDelete();

            $table->string('invoice_number', 100)->nullable();
            $table->date('purchase_date');
            $table->string('status', 50)->default('Posted');
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('account_id');
            $table->index('vendor_id');
            $table->index('warehouse_id');
            $table->index('purchase_date');
            $table->index('status');
        });

        Schema::create('tbl_purchase_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('account_id')
                ->constrained('tbl_accounts')
                ->cascadeOnDelete();

            $table->foreignId('purchase_id')
                ->constrained('tbl_purchases')
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->constrained('tbl_products')
                ->restrictOnDelete();

            $table->integer('quantity');
            $table->decimal('line_total', 12, 2);
            $table->decimal('unit_cost', 10, 4);
            $table->timestamp('created_at')->nullable();

            $table->index('account_id');
            $table->index('purchase_id');
            $table->index('product_id');
        });

        Schema::create('tbl_inventory_ledger', function (Blueprint $table) {
            $table->id();

            $table->foreignId('account_id')
                ->constrained('tbl_accounts')
                ->cascadeOnDelete();

            $table->foreignId('warehouse_id')
                ->constrained('tbl_warehouses')
                ->restrictOnDelete();

            $table->foreignId('product_id')
                ->constrained('tbl_products')
                ->restrictOnDelete();

            $table->string('movement_type', 50);
            $table->integer('quantity_delta');
            $table->decimal('unit_cost', 10, 4)->default(0);
            $table->decimal('total_cost', 12, 4)->default(0);
            $table->string('source_type', 100)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->dateTime('movement_at');
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('account_id', 'inventory_ledger_account_index');
            $table->index('warehouse_id', 'inventory_ledger_warehouse_index');
            $table->index('product_id', 'inventory_ledger_product_index');
            $table->index('movement_type', 'inventory_ledger_movement_type_index');
            $table->index('movement_at', 'inventory_ledger_movement_at_index');
            $table->index(['source_type', 'source_id'], 'inventory_ledger_source_index');
        });

        Schema::table('tbl_services', function (Blueprint $table) {
            $table->foreignId('warehouse_id')
                ->nullable()
                ->after('location_id')
                ->constrained('tbl_warehouses')
                ->nullOnDelete();
        });

        Schema::table('tbl_transactions', function (Blueprint $table) {
            $table->decimal('unit_cost', 10, 4)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('tbl_transactions', function (Blueprint $table) {
            $table->decimal('unit_cost', 8, 2)->nullable()->change();
        });

        Schema::table('tbl_services', function (Blueprint $table) {
            $table->dropConstrainedForeignId('warehouse_id');
        });

        Schema::dropIfExists('tbl_inventory_ledger');
        Schema::dropIfExists('tbl_purchase_items');
        Schema::dropIfExists('tbl_purchases');
    }
};
