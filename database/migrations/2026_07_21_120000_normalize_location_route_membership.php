<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_route_locations', function (Blueprint $table) {
            $table->boolean('is_primary')
                ->default(false)
                ->after('stop_order');
        });

        $timestamp = now();

        foreach (DB::table('tbl_locations')
            ->select(['id', 'account_id', 'route_id'])
            ->orderBy('id')
            ->cursor() as $location) {
            $routeLocationQuery = DB::table('tbl_route_locations')
                ->where('account_id', $location->account_id)
                ->where('location_id', $location->id);

            if ($location->route_id !== null) {
                $routeLocation = (clone $routeLocationQuery)
                    ->where('route_id', $location->route_id)
                    ->first();

                if (! $routeLocation) {
                    $nextStopOrder = (int) DB::table('tbl_route_locations')
                        ->where('account_id', $location->account_id)
                        ->where('route_id', $location->route_id)
                        ->max('stop_order') + 1;

                    DB::table('tbl_route_locations')->insert([
                        'account_id' => $location->account_id,
                        'route_id' => $location->route_id,
                        'location_id' => $location->id,
                        'stop_order' => $nextStopOrder,
                        'is_primary' => true,
                        'created_at' => $timestamp,
                    ]);

                    $routeLocationQuery->where('route_id', '!=', $location->route_id)->update([
                        'is_primary' => false,
                    ]);

                    continue;
                }

                $routeLocationQuery->update(['is_primary' => false]);

                DB::table('tbl_route_locations')
                    ->where('id', $routeLocation->id)
                    ->update(['is_primary' => true]);

                continue;
            }

            $fallbackPrimaryId = (clone $routeLocationQuery)
                ->orderBy('stop_order')
                ->orderBy('id')
                ->value('id');

            if ($fallbackPrimaryId === null) {
                continue;
            }

            $routeLocationQuery->update(['is_primary' => false]);

            DB::table('tbl_route_locations')
                ->where('id', $fallbackPrimaryId)
                ->update(['is_primary' => true]);
        }

        Schema::table('tbl_locations', function (Blueprint $table) {
            $table->dropForeign(['route_id']);
            $table->dropIndex(['route_id']);
        });

        Schema::table('tbl_locations', function (Blueprint $table) {
            $table->dropColumn('route_id');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_locations', function (Blueprint $table) {
            $table->foreignId('route_id')
                ->nullable()
                ->after('account_id')
                ->constrained('tbl_routes')
                ->restrictOnDelete();
        });

        foreach (DB::table('tbl_locations')
            ->select(['id', 'account_id'])
            ->orderBy('id')
            ->cursor() as $location) {
            $primaryRouteId = DB::table('tbl_route_locations')
                ->where('account_id', $location->account_id)
                ->where('location_id', $location->id)
                ->orderByDesc('is_primary')
                ->orderBy('stop_order')
                ->orderBy('id')
                ->value('route_id');

            DB::table('tbl_locations')
                ->where('id', $location->id)
                ->update([
                    'route_id' => $primaryRouteId,
                ]);
        }

        Schema::table('tbl_route_locations', function (Blueprint $table) {
            $table->dropColumn('is_primary');
        });
    }
};
