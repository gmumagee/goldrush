<?php

use App\Models\DataDictionary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tbl_services', 'notes')) {
            Schema::table('tbl_services', function (Blueprint $table) {
                $table->text('notes')
                    ->nullable()
                    ->after('service_type');
            });
        }

        $now = now();

        DB::table('tbl_data_dictionary')->updateOrInsert(
            [
                'account_id' => null,
                'name' => DataDictionary::GROUP_SERVICE_TYPE,
                'value' => 'location_service',
            ],
            [
                'label' => 'Location Service',
                'sort_order' => 10,
                'is_active' => true,
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );

        DB::table('tbl_data_dictionary')->updateOrInsert(
            [
                'account_id' => null,
                'name' => DataDictionary::GROUP_SERVICE_TYPE,
                'value' => 'maintenance_service',
            ],
            [
                'label' => 'Maintenance Service',
                'sort_order' => 20,
                'is_active' => true,
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );
    }

    public function down(): void
    {
        DB::table('tbl_data_dictionary')
            ->whereNull('account_id')
            ->where('name', DataDictionary::GROUP_SERVICE_TYPE)
            ->where('value', 'maintenance_service')
            ->delete();

        if (Schema::hasColumn('tbl_services', 'notes')) {
            Schema::table('tbl_services', function (Blueprint $table) {
                $table->dropColumn('notes');
            });
        }
    }
};
