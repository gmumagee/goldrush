<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Location;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ServiceCreatedByBackfillTest extends TestCase
{
    use DatabaseMigrations;

    public function test_existing_services_are_backfilled_with_the_assigned_user_as_creator(): void
    {
        $migrationPath = database_path('migrations/2026_07_22_140000_add_created_by_user_id_to_services_table.php');

        Artisan::call('migrate:rollback', [
            '--path' => $migrationPath,
            '--realpath' => true,
        ]);

        $this->assertFalse(Schema::hasColumn('tbl_services', 'created_by_user_id'));

        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = Account::create([
            'account_name' => 'Backfill Account',
            'slug' => 'backfill-account-'.uniqid(),
            'status' => Account::STATUS_ACTIVE,
            'billing_email' => 'backfill@example.com',
        ]);
        $location = Location::create([
            'account_id' => $account->id,
            'location_name' => 'Backfill Stop',
            'address' => '500 Legacy Way',
            'city' => 'Toronto',
            'state' => 'ON',
            'zip_code' => 'M5V1A1',
        ]);

        $serviceId = DB::table('tbl_services')->insertGetId([
            'account_id' => $account->id,
            'location_id' => $location->id,
            'warehouse_id' => null,
            'user_id' => $user->id,
            'closed_by_user_id' => null,
            'service_type' => Service::TYPE_MAINTENANCE,
            'notes' => 'Legacy backfill service',
            'service_date' => '2026-07-22 00:00:00',
            'scheduled_at' => '2026-07-22 00:00:00',
            'opened_at' => null,
            'completed_at' => null,
            'closed_at' => null,
            'amount_collected' => null,
            'status' => Service::STATUS_AWAITING,
        ]);

        Artisan::call('migrate', [
            '--path' => $migrationPath,
            '--realpath' => true,
        ]);

        $this->assertTrue(Schema::hasColumn('tbl_services', 'created_by_user_id'));
        $this->assertDatabaseHas('tbl_services', [
            'id' => $serviceId,
            'user_id' => $user->id,
            'created_by_user_id' => $user->id,
        ]);
    }
}
