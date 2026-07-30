<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_upgrade_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->nullable()->constrained('tbl_accounts')->nullOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('tbl_users')->nullOnDelete();
            $table->foreignId('current_plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->foreignId('requested_plan_id')->constrained('plans')->restrictOnDelete();
            $table->string('contact_email')->nullable();
            $table->string('source', 100);
            $table->unsignedInteger('machine_count')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_upgrade_requests');
    }
};
