<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LegacyLocationContactBackfillTest extends TestCase
{
    use DatabaseMigrations;

    public function test_legacy_location_contact_data_is_backfilled_before_columns_are_dropped(): void
    {
        $migrationPath = database_path('migrations/2026_07_21_130000_remove_legacy_location_contact_columns.php');

        Artisan::call('migrate:rollback', [
            '--path' => $migrationPath,
            '--realpath' => true,
        ]);

        $this->assertTrue(Schema::hasColumns('tbl_locations', ['contact_name', 'contact_phone', 'contact_email']));

        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = Account::create([
            'account_name' => 'Legacy Contact Account',
            'slug' => 'legacy-contact-account-'.uniqid(),
            'status' => 'active',
        ]);

        AccountUser::create([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'role' => AccountUser::ROLE_OWNER,
            'status' => AccountUser::STATUS_ACTIVE,
        ]);

        $locationId = DB::table('tbl_locations')->insertGetId([
            'account_id' => $account->id,
            'location_name' => 'Legacy Lobby',
            'address' => '500 Legacy Way',
            'city' => 'Toronto',
            'state' => 'ON',
            'zip_code' => 'M5V1A1',
            'contact_name' => 'Legacy Front Desk',
            'contact_phone' => '555-321-0000',
            'contact_email' => 'legacy.lobby@example.com',
        ]);

        $this->assertDatabaseMissing('tbl_location_contacts', [
            'account_id' => $account->id,
            'location_id' => $locationId,
        ]);

        Artisan::call('migrate', [
            '--path' => $migrationPath,
            '--realpath' => true,
        ]);

        $this->assertFalse(Schema::hasColumn('tbl_locations', 'contact_name'));
        $this->assertFalse(Schema::hasColumn('tbl_locations', 'contact_phone'));
        $this->assertFalse(Schema::hasColumn('tbl_locations', 'contact_email'));

        $locationContact = DB::table('tbl_location_contacts')
            ->where('account_id', $account->id)
            ->where('location_id', $locationId)
            ->first();

        $this->assertNotNull($locationContact);
        $this->assertSame(1, (int) $locationContact->is_primary);

        $contact = DB::table('tbl_contacts')
            ->where('id', $locationContact->contact_id)
            ->first();

        $this->assertNotNull($contact);
        $this->assertSame('Legacy Front Desk', $contact->organization);
        $this->assertSame('555-321-0000', $contact->phone);
        $this->assertSame('legacy.lobby@example.com', $contact->email);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('locations.show', $locationId))
            ->assertOk()
            ->assertSeeText('Legacy Front Desk')
            ->assertSeeText('555-321-0000')
            ->assertSeeText('legacy.lobby@example.com');
    }
}
