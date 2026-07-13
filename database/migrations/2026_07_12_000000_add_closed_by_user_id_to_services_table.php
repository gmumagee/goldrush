<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Track which user finalized the collected amount entry so closure
        // ownership is audited separately from the assigned technician.
        if (! Schema::hasColumn('tbl_services', 'closed_by_user_id')) {
            Schema::table('tbl_services', function (Blueprint $table) {
                $table->foreignId('closed_by_user_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('tbl_users')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tbl_services', 'closed_by_user_id')) {
            Schema::table('tbl_services', function (Blueprint $table) {
                $table->dropConstrainedForeignId('closed_by_user_id');
            });
        }
    }
};
