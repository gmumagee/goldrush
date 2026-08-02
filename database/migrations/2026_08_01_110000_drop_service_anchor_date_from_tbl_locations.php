<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('tbl_locations', 'service_anchor_date')) {
            Schema::table('tbl_locations', function (Blueprint $table) {
                $table->dropColumn('service_anchor_date');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('tbl_locations', 'service_anchor_date')) {
            Schema::table('tbl_locations', function (Blueprint $table) {
                $table->date('service_anchor_date')->nullable()->after('service_interval_days');
            });
        }
    }
};
