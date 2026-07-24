<?php

use App\Models\Location;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_locations', function (Blueprint $table) {
            $table->boolean('is_inventory')
                ->nullable()
                ->after('zip_code');
        });

        $missingAccountIds = DB::table('tbl_accounts as accounts')
            ->leftJoin('tbl_locations as inventory_locations', function ($join) {
                $join->on('inventory_locations.account_id', '=', 'accounts.id')
                    ->where('inventory_locations.is_inventory', true);
            })
            ->whereNull('inventory_locations.id')
            ->orderBy('accounts.id')
            ->pluck('accounts.id');

        foreach ($missingAccountIds as $accountId) {
            DB::table('tbl_locations')->insert([
                'account_id' => (int) $accountId,
                'location_name' => Location::INVENTORY_LOCATION_NAME,
                'address' => null,
                'city' => null,
                'state' => null,
                'zip_code' => null,
                'is_inventory' => true,
            ]);
        }

        Schema::table('tbl_locations', function (Blueprint $table) {
            // Deliberately rely on MySQL allowing multiple NULL values so the
            // unique pair only constrains the single TRUE inventory row.
            $table->unique(['account_id', 'is_inventory'], 'tbl_locations_account_inventory_unique');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_locations', function (Blueprint $table) {
            $table->dropUnique('tbl_locations_account_inventory_unique');
            $table->dropColumn('is_inventory');
        });
    }
};
