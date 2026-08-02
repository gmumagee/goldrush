<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\DataDictionary;
use App\Models\Location;
use App\Models\Machine;
use App\Models\RouteLocation;
use App\Models\User;
use App\Models\VendingRoute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MachineMetadataTest extends TestCase
{
    use RefreshDatabase;

    public function test_machine_schema_includes_optional_key_and_telemetry_columns_with_null_defaults(): void
    {
        $this->assertTrue(Schema::hasColumns('tbl_machines', ['key_number', 'telemetry_id']));

        $account = $this->createAccount('Schema Machine Account');
        $location = $this->createCustomerLocation($account, $this->createRoute($account, 'Schema Route'), 'Schema Stop');

        $machine = Machine::create([
            'account_id' => $account->id,
            'location_id' => $location->id,
            'type' => 'snack',
            'serial_number' => 'SCHEMA-100',
            'model' => 'Schema Model',
            'status' => Machine::STATUS_ACTIVE,
            'installed_on' => '2026-07-31',
        ]);

        $this->assertNull($machine->fresh()->key_number);
        $this->assertNull($machine->fresh()->telemetry_id);
    }

    public function test_machine_create_and_update_persist_key_number_and_telemetry_id(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Machine Metadata Account');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);
        $this->createMachineStatusDictionary();

        $route = $this->createRoute($account, 'Machine Metadata Route');
        $location = $this->createCustomerLocation($account, $route, 'Machine Metadata Stop');

        $createResponse = $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('machines.store'), [
                'location_id' => $location->id,
                'type' => 'snack',
                'serial_number' => 'META-100',
                'key_number' => 'KEY-100',
                'telemetry_id' => 'TEL-100',
                'model' => 'Metadata Model',
                'status' => Machine::STATUS_ACTIVE,
                'installed_on' => '07-31-2026',
            ]);

        $machine = Machine::query()
            ->where('account_id', $account->id)
            ->where('serial_number', 'META-100')
            ->firstOrFail();

        $createResponse
            ->assertRedirect(route('machines.show', $machine))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tbl_machines', [
            'id' => $machine->id,
            'key_number' => 'KEY-100',
            'telemetry_id' => 'TEL-100',
        ]);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->patch(route('machines.update', $machine), [
                'location_id' => $location->id,
                'type' => 'snack',
                'serial_number' => 'META-100',
                'key_number' => 'KEY-200',
                'telemetry_id' => 'TEL-200',
                'model' => 'Metadata Model Updated',
                'status' => Machine::STATUS_ACTIVE,
                'installed_on' => '08-01-2026',
            ])
            ->assertRedirect(route('machines.show', $machine->id))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tbl_machines', [
            'id' => $machine->id,
            'key_number' => 'KEY-200',
            'telemetry_id' => 'TEL-200',
            'installed_on' => '2026-08-01 00:00:00',
        ]);
    }

    public function test_telemetry_id_is_unique_per_account_but_can_repeat_across_accounts(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Telemetry Primary Account');
        $otherAccount = $this->createAccount('Telemetry Other Account');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);
        $this->createMachineStatusDictionary();

        $route = $this->createRoute($account, 'Telemetry Route');
        $location = $this->createCustomerLocation($account, $route, 'Telemetry Stop');
        $otherRoute = $this->createRoute($otherAccount, 'Telemetry Other Route');
        $otherLocation = $this->createCustomerLocation($otherAccount, $otherRoute, 'Telemetry Other Stop');

        Machine::create([
            'account_id' => $account->id,
            'location_id' => $location->id,
            'type' => 'snack',
            'serial_number' => 'TEL-ACCOUNT-100',
            'key_number' => 'KEY-A',
            'telemetry_id' => 'TEL-DUPLICATE',
            'model' => 'Primary Model',
            'status' => Machine::STATUS_ACTIVE,
            'installed_on' => '2026-07-31',
        ]);

        Machine::create([
            'account_id' => $otherAccount->id,
            'location_id' => $otherLocation->id,
            'type' => 'combo',
            'serial_number' => 'TEL-OTHER-100',
            'key_number' => 'KEY-B',
            'telemetry_id' => 'TEL-SHARED',
            'model' => 'Other Model',
            'status' => Machine::STATUS_ACTIVE,
            'installed_on' => '2026-07-31',
        ]);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->from(route('machines.create'))
            ->post(route('machines.store'), [
                'location_id' => $location->id,
                'type' => 'snack',
                'serial_number' => 'TEL-ACCOUNT-200',
                'key_number' => 'KEY-C',
                'telemetry_id' => 'TEL-DUPLICATE',
                'model' => 'Duplicate Model',
                'status' => Machine::STATUS_ACTIVE,
                'installed_on' => '08-01-2026',
            ])
            ->assertRedirect(route('machines.create'))
            ->assertSessionHasErrors('telemetry_id');

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('machines.store'), [
                'location_id' => $location->id,
                'type' => 'snack',
                'serial_number' => 'TEL-ACCOUNT-300',
                'key_number' => 'KEY-D',
                'telemetry_id' => 'TEL-SHARED',
                'model' => 'Shared Model',
                'status' => Machine::STATUS_ACTIVE,
                'installed_on' => '08-01-2026',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tbl_machines', [
            'account_id' => $account->id,
            'serial_number' => 'TEL-ACCOUNT-300',
            'telemetry_id' => 'TEL-SHARED',
        ]);
    }

    public function test_machine_detail_shows_key_number_and_telemetry_id(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Show Metadata Account');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);

        $route = $this->createRoute($account, 'Show Metadata Route');
        $location = $this->createCustomerLocation($account, $route, 'Show Metadata Stop');
        $machine = Machine::create([
            'account_id' => $account->id,
            'location_id' => $location->id,
            'type' => 'snack',
            'serial_number' => 'SHOW-100',
            'key_number' => 'KEY-SHOW',
            'telemetry_id' => 'TEL-SHOW',
            'model' => 'Show Model',
            'status' => Machine::STATUS_ACTIVE,
            'installed_on' => '2026-07-31',
        ]);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('machines.show', $machine))
            ->assertOk()
            ->assertSeeText('Key Number')
            ->assertSeeText('Telemetry ID')
            ->assertSeeText('KEY-SHOW')
            ->assertSeeText('TEL-SHOW');
    }

    public function test_machine_store_and_update_allow_blank_key_number_and_telemetry_id(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Blank Metadata Account');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);
        $this->createMachineStatusDictionary();

        $route = $this->createRoute($account, 'Blank Metadata Route');
        $location = $this->createCustomerLocation($account, $route, 'Blank Metadata Stop');

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('machines.store'), [
                'location_id' => $location->id,
                'type' => 'snack',
                'serial_number' => 'BLANK-100',
                'key_number' => '',
                'telemetry_id' => '',
                'model' => 'Blank Model',
                'status' => Machine::STATUS_ACTIVE,
                'installed_on' => '08-01-2026',
            ])
            ->assertSessionHasNoErrors();

        $machine = Machine::query()
            ->where('account_id', $account->id)
            ->where('serial_number', 'BLANK-100')
            ->firstOrFail();

        $this->assertDatabaseHas('tbl_machines', [
            'id' => $machine->id,
            'key_number' => null,
            'telemetry_id' => null,
        ]);

        $machine->update([
            'key_number' => 'KEY-BLANK',
            'telemetry_id' => 'TEL-BLANK',
        ]);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->patch(route('machines.update', $machine), [
                'location_id' => $location->id,
                'type' => 'snack',
                'serial_number' => 'BLANK-100',
                'key_number' => '',
                'telemetry_id' => '',
                'model' => 'Blank Model Updated',
                'status' => Machine::STATUS_ACTIVE,
                'installed_on' => '08-01-2026',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tbl_machines', [
            'id' => $machine->id,
            'key_number' => null,
            'telemetry_id' => null,
        ]);
    }

    protected function createMachineStatusDictionary(): void
    {
        DataDictionary::create([
            'account_id' => null,
            'name' => DataDictionary::GROUP_MACHINE_STATUS,
            'value' => Machine::STATUS_ACTIVE,
            'label' => 'Active',
            'sort_order' => 10,
            'is_active' => true,
        ]);
    }

    protected function createAccount(string $name): Account
    {
        return Account::create([
            'account_name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)).'-'.uniqid(),
            'status' => 'active',
            'billing_email' => strtolower(str_replace(' ', '.', $name)).'@example.com',
        ]);
    }

    protected function attachUserToAccount(User $user, Account $account, string $role): void
    {
        AccountUser::create([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => 'active',
        ]);
    }

    protected function createRoute(Account $account, string $name): VendingRoute
    {
        return VendingRoute::create([
            'account_id' => $account->id,
            'route_name' => $name,
            'description' => $name.' description',
        ]);
    }

    protected function createCustomerLocation(Account $account, VendingRoute $route, string $name): Location
    {
        $location = Location::create([
            'account_id' => $account->id,
            'location_name' => $name,
            'address' => '123 Metadata Street',
            'city' => 'Toronto',
            'state' => 'ON',
            'zip_code' => 'M1M1M1',
        ]);

        RouteLocation::create([
            'account_id' => $account->id,
            'route_id' => $route->id,
            'location_id' => $location->id,
            'stop_order' => 1,
            'is_primary' => true,
        ]);

        return $location;
    }
}
