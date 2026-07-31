<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tbl_calendar_reminders')) {
            return;
        }

        if (Schema::hasColumn('tbl_calendar_reminders', 'dismissed_by_user_id')) {
            return;
        }

        Schema::table('tbl_calendar_reminders', function (Blueprint $table) {
            $table->foreignId('dismissed_by_user_id')
                ->nullable()
                ->constrained('tbl_users')
                ->nullOnDelete()
                ->cascadeOnUpdate()
                ->after('dismissed_at');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tbl_calendar_reminders')) {
            return;
        }

        if (! Schema::hasColumn('tbl_calendar_reminders', 'dismissed_by_user_id')) {
            return;
        }

        Schema::table('tbl_calendar_reminders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dismissed_by_user_id');
        });
    }
};
