<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_audit_log', function (Blueprint $table) {
            $table->id();

            $table->foreignId('account_id')
                ->nullable()
                ->constrained('tbl_accounts')
                ->nullOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('tbl_users')
                ->nullOnDelete();

            $table->string('auditable_type', 191);
            $table->unsignedBigInteger('auditable_id');
            $table->string('event', 50);
            $table->json('changes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['auditable_type', 'auditable_id'], 'audit_log_auditable_index');
            $table->index('account_id', 'audit_log_account_index');
            $table->index('user_id', 'audit_log_user_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_audit_log');
    }
};
