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
        // Services are now owned by a location, so add the new foreign key
        // before backfilling data from any legacy machine-based records.
        Schema::table('tbl_services', function (Blueprint $table) {
            $table->foreignId('location_id')
                ->nullable()
                ->after('account_id')
                ->constrained('tbl_locations')
                ->restrictOnDelete();
        });

        $machineLocations = DB::table('tbl_machines')
            ->pluck('location_id', 'id');

        foreach (DB::table('tbl_services')->select(['id', 'machine_id', 'status'])->orderBy('id')->cursor() as $service) {
            DB::table('tbl_services')
                ->where('id', $service->id)
                ->update([
                    'location_id' => $machineLocations[$service->machine_id] ?? null,
                    'status' => match ($service->status) {
                        'open' => Service::STATUS_SERVICE_OPEN,
                        'completed', 'cancelled' => Service::STATUS_SERVICE_CLOSED,
                        default => $service->status ?: Service::STATUS_AWAITING_SERVICE,
                    },
                ]);
        }

        // Keep the legacy machine_id column for compatibility, but make it
        // nullable so new location services are not forced onto one machine.
        Schema::table('tbl_services', function (Blueprint $table) {
            $table->foreignId('machine_id')->nullable()->change();
            $table->foreignId('location_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        // Roll back the location foreign key, leaving the legacy machine_id
        // column in place because older records may still reference it.
        Schema::table('tbl_services', function (Blueprint $table) {
            $table->dropConstrainedForeignId('location_id');
        });
    }
};
