<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_expenses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('account_id')
                ->constrained('tbl_accounts')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('location_id')
                ->nullable()
                ->constrained('tbl_locations')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->string('category', 50);
            $table->decimal('amount', 10, 2);
            $table->date('expense_date');
            $table->text('description')->nullable();
            $table->string('vendor', 255)->nullable();

            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('tbl_users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->index(['account_id', 'expense_date'], 'expenses_account_date_index');
            $table->index(['account_id', 'location_id'], 'expenses_account_location_index');
            $table->index(['account_id', 'category'], 'expenses_account_category_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_expenses');
    }
};
