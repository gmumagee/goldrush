<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('account_name');
            $table->string('slug')->unique();
            $table->string('status', 50)->default('active');
            $table->string('billing_email')->nullable();
            $table->string('phone', 50)->nullable();
        });

        Schema::create('tbl_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('status', 50)->default('active');
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
        });

        Schema::create('tbl_account_users', function (Blueprint $table) {
            $table->id();

            $table->foreignId('account_id')
                ->constrained('tbl_accounts')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('tbl_users')
                ->cascadeOnDelete();

            $table->string('role', 50)->default('viewer');
            $table->string('status', 50)->default('active');

            $table->unique(['account_id', 'user_id']);
            $table->index('role');
        });

        Schema::create('tbl_warehouses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('account_id')
                ->constrained('tbl_accounts')
                ->cascadeOnDelete();

            $table->string('warehouse_name');
            $table->string('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('zip_code', 20)->nullable();

            $table->index('account_id');
            $table->index('warehouse_name');
        });

        Schema::create('tbl_vendors', function (Blueprint $table) {
            $table->id();

            $table->foreignId('account_id')
                ->constrained('tbl_accounts')
                ->cascadeOnDelete();

            $table->string('vendor_name');
            $table->string('location')->nullable();

            $table->index('account_id');
            $table->index('vendor_name');
        });

        Schema::create('tbl_products', function (Blueprint $table) {
            $table->id();

            $table->foreignId('account_id')
                ->constrained('tbl_accounts')
                ->cascadeOnDelete();

            $table->foreignId('vendor_id')
                ->nullable()
                ->constrained('tbl_vendors')
                ->nullOnDelete();

            $table->string('sku', 100)->nullable();
            $table->string('product_name');
            $table->string('barcode', 100)->nullable();

            $table->unique(['account_id', 'sku']);
            $table->index('account_id');
            $table->index('vendor_id');
            $table->index('barcode');
        });

        Schema::create('tbl_routes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('account_id')
                ->constrained('tbl_accounts')
                ->cascadeOnDelete();

            $table->string('route_name');
            $table->text('description')->nullable();

            $table->index('account_id');
            $table->index('route_name');
        });

        Schema::create('tbl_locations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('account_id')
                ->constrained('tbl_accounts')
                ->cascadeOnDelete();

            $table->foreignId('route_id')
                ->constrained('tbl_routes')
                ->restrictOnDelete();

            $table->string('location_name');
            $table->string('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('zip_code', 20)->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_phone', 50)->nullable();
            $table->string('contact_email')->nullable();

            $table->index('account_id');
            $table->index('route_id');
            $table->index('location_name');
        });

        Schema::create('tbl_machines', function (Blueprint $table) {
            $table->id();

            $table->foreignId('account_id')
                ->constrained('tbl_accounts')
                ->cascadeOnDelete();

            $table->foreignId('location_id')
                ->constrained('tbl_locations')
                ->restrictOnDelete();

            $table->string('type', 100);
            $table->string('serial_number')->nullable();
            $table->string('model')->nullable();
            $table->string('status', 50)->default('active');
            $table->date('installed_on')->nullable();

            $table->unique(['account_id', 'serial_number']);
            $table->index('account_id');
            $table->index('location_id');
            $table->index('status');
        });

        Schema::create('tbl_bins', function (Blueprint $table) {
            $table->id();

            $table->foreignId('account_id')
                ->constrained('tbl_accounts')
                ->cascadeOnDelete();

            $table->foreignId('machine_id')
                ->constrained('tbl_machines')
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->nullable()
                ->constrained('tbl_products')
                ->nullOnDelete();

            $table->string('bin_code', 50);
            $table->unsignedInteger('capacity')->default(0);
            $table->decimal('price', 8, 2)->nullable();

            $table->unique(['machine_id', 'bin_code']);
            $table->index('account_id');
            $table->index('machine_id');
            $table->index('product_id');
        });

        Schema::create('tbl_services', function (Blueprint $table) {
            $table->id();

            $table->foreignId('account_id')
                ->constrained('tbl_accounts')
                ->cascadeOnDelete();

            $table->foreignId('machine_id')
                ->constrained('tbl_machines')
                ->restrictOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('tbl_users')
                ->nullOnDelete();

            $table->foreignId('closed_by_user_id')
                ->nullable()
                ->constrained('tbl_users')
                ->nullOnDelete();

            $table->string('service_type', 50);
            $table->dateTime('service_date');
            $table->decimal('amount_collected', 10, 2)->nullable();
            $table->string('status', 50)->default('open');

            $table->index('account_id');
            $table->index('machine_id');
            $table->index('user_id');
            $table->index('closed_by_user_id');
            $table->index('service_date');
            $table->index('status');
        });

        Schema::create('tbl_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('account_id')
                ->constrained('tbl_accounts')
                ->cascadeOnDelete();

            $table->foreignId('service_id')
                ->constrained('tbl_services')
                ->cascadeOnDelete();

            $table->foreignId('warehouse_id')
                ->nullable()
                ->constrained('tbl_warehouses')
                ->nullOnDelete();

            $table->foreignId('bin_id')
                ->constrained('tbl_bins')
                ->restrictOnDelete();

            $table->foreignId('product_id')
                ->nullable()
                ->constrained('tbl_products')
                ->nullOnDelete();

            $table->string('transaction_type', 50);
            $table->integer('quantity');
            $table->decimal('price', 8, 2)->nullable();
            $table->decimal('unit_cost', 8, 2)->nullable();

            $table->index('account_id');
            $table->index('service_id');
            $table->index('warehouse_id');
            $table->index('bin_id');
            $table->index('product_id');
            $table->index('transaction_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_transactions');
        Schema::dropIfExists('tbl_services');
        Schema::dropIfExists('tbl_bins');
        Schema::dropIfExists('tbl_machines');
        Schema::dropIfExists('tbl_locations');
        Schema::dropIfExists('tbl_routes');
        Schema::dropIfExists('tbl_products');
        Schema::dropIfExists('tbl_vendors');
        Schema::dropIfExists('tbl_warehouses');
        Schema::dropIfExists('tbl_account_users');
        Schema::dropIfExists('tbl_users');
        Schema::dropIfExists('tbl_accounts');
    }
};
