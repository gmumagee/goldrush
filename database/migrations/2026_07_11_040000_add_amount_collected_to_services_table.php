<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Track the service completion timestamp separately from final closure
        // and keep the final collected amount directly on the service record.
        if (! Schema::hasColumn('tbl_services', 'completed_at')) {
            Schema::table('tbl_services', function (Blueprint $table) {
                $table->dateTime('completed_at')
                    ->nullable()
                    ->after('opened_at');
            });
        }

        if (! Schema::hasColumn('tbl_services', 'amount_collected')) {
            Schema::table('tbl_services', function (Blueprint $table) {
                $table->decimal('amount_collected', 10, 2)
                    ->nullable()
                    ->after('closed_at');
            });
        }

        DB::table('tbl_services')
            ->where('status', 'Service Closed')
            ->whereNull('completed_at')
            ->whereNotNull('closed_at')
            ->update([
                'completed_at' => DB::raw('closed_at'),
            ]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('tbl_services', 'completed_at')) {
            Schema::table('tbl_services', function (Blueprint $table) {
                $table->dropColumn('completed_at');
            });
        }

        if (Schema::hasColumn('tbl_services', 'amount_collected')) {
            Schema::table('tbl_services', function (Blueprint $table) {
                $table->dropColumn('amount_collected');
            });
        }
    }
};
