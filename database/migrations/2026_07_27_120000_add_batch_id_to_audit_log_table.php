<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_audit_log', function (Blueprint $table) {
            $table->string('batch_id', 36)->nullable()->after('event');
            $table->index('batch_id', 'audit_log_batch_index');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_audit_log', function (Blueprint $table) {
            $table->dropIndex('audit_log_batch_index');
            $table->dropColumn('batch_id');
        });
    }
};
