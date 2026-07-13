<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_routes', function (Blueprint $table) {
            $table->string('scheduled_day', 20)->nullable()->after('description');
        });

        Schema::create('tbl_route_locations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('account_id')
                ->constrained('tbl_accounts')
                ->cascadeOnDelete();

            $table->foreignId('route_id')
                ->constrained('tbl_routes')
                ->cascadeOnDelete();

            $table->foreignId('location_id')
                ->constrained('tbl_locations')
                ->restrictOnDelete();

            $table->unsignedInteger('stop_order');
            $table->timestamp('created_at')->nullable();

            $table->unique(['account_id', 'route_id', 'location_id'], 'route_location_unique');
            $table->unique(['account_id', 'route_id', 'stop_order'], 'route_stop_order_unique');
            $table->index('account_id', 'route_locations_account_id_index');
            $table->index('route_id', 'route_locations_route_id_index');
            $table->index('location_id', 'route_locations_location_id_index');
            $table->index('stop_order', 'route_locations_stop_order_index');
        });

        Schema::table('tbl_locations', function (Blueprint $table) {
            $table->foreignId('route_id')->nullable()->change();
        });

        $timestamp = now();
        $locationsByRoute = DB::table('tbl_locations')
            ->select(['id', 'account_id', 'route_id'])
            ->whereNotNull('route_id')
            ->orderBy('route_id')
            ->orderBy('id')
            ->get()
            ->groupBy('route_id');

        foreach ($locationsByRoute as $routeId => $locations) {
            foreach ($locations->values() as $index => $location) {
                $exists = DB::table('tbl_route_locations')
                    ->where('account_id', $location->account_id)
                    ->where('route_id', $routeId)
                    ->where('location_id', $location->id)
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('tbl_route_locations')->insert([
                    'account_id' => $location->account_id,
                    'route_id' => $routeId,
                    'location_id' => $location->id,
                    'stop_order' => $index + 1,
                    'created_at' => $timestamp,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('tbl_locations', function (Blueprint $table) {
            $table->foreignId('route_id')->nullable(false)->change();
        });

        Schema::dropIfExists('tbl_route_locations');

        Schema::table('tbl_routes', function (Blueprint $table) {
            $table->dropColumn('scheduled_day');
        });
    }
};
