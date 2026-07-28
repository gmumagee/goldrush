<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_account_backups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('tbl_accounts')->restrictOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('tbl_users')->restrictOnDelete();
            $table->string('status', 20);
            $table->string('file_disk', 50)->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->json('row_counts')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'created_at'], 'account_backups_account_created_index');
            $table->index(['status', 'created_at'], 'account_backups_status_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_account_backups');
    }
};
