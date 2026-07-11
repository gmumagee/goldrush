<?php

use App\Models\Service;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Normalize any lingering legacy values before locking in the new
        // workflow defaults at the database level.
        DB::table('tbl_services')
            ->where('status', 'open')
            ->update(['status' => Service::STATUS_SERVICE_OPEN]);

        DB::table('tbl_services')
            ->whereIn('status', ['completed', 'cancelled'])
            ->update(['status' => Service::STATUS_SERVICE_CLOSED]);

        Schema::table('tbl_services', function (Blueprint $table) {
            $table->string('service_type', 50)->default(Service::TYPE_LOCATION_SERVICE)->change();
            $table->string('status', 50)->default(Service::STATUS_AWAITING_SERVICE)->change();
        });
    }

    public function down(): void
    {
        Schema::table('tbl_services', function (Blueprint $table) {
            $table->string('service_type', 50)->default(null)->change();
            $table->string('status', 50)->default('open')->change();
        });
    }
};
