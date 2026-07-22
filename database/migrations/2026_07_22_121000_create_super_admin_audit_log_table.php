<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_super_admin_audit_log', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('tbl_users')
                ->nullOnDelete();

            $table->foreignId('account_id')
                ->nullable()
                ->constrained('tbl_accounts')
                ->nullOnDelete();

            $table->string('action', 100);
            $table->timestamp('created_at')->useCurrent();

            $table->index('user_id');
            $table->index('account_id');
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_super_admin_audit_log');
    }
};
