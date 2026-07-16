<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tbl_services', 'scheduled_at')) {
            Schema::table('tbl_services', function (Blueprint $table) {
                $table->dateTime('scheduled_at')
                    ->nullable()
                    ->after('service_date');

                $table->index('scheduled_at');
            });
        }

        DB::table('tbl_services')
            ->whereNull('scheduled_at')
            ->whereNotNull('service_date')
            ->update([
                'scheduled_at' => DB::raw('service_date'),
            ]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('tbl_services', 'scheduled_at')) {
            Schema::table('tbl_services', function (Blueprint $table) {
                $table->dropIndex(['scheduled_at']);
                $table->dropColumn('scheduled_at');
            });
        }
    }
};
