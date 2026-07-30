<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\DataDictionary;
use App\Models\Location;
use App\Models\Machine;
use App\Models\Plan;
use App\Models\PlanUpgradeRequest;
use App\Models\RouteLocation;
use App\Models\User;
use App\Models\VendingRoute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountPlanEnforcementTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_accounts_default_to_free_plan(): void
    {
        $account = $this->createAccount('Default Free Account');

        $this->assertSame(Plan::FREE_ID, (int) $account->plan_id);
        $this->assertSame('Free', $account->plan?->name);
    }

    public function test_free_plan_blocks_eleventh_machine_and_starter_allows_it_even_when_existing_machines_are_in_inventory(): void
    {
        $this->createMachineStatusDictionary();

        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Inventory Count Account');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);

        $inventoryLocation = $account->inventoryLocation()->firstOrFail();

        for ($index = 1; $index <= 10; $index++) {
            $this->createMachine($account, $inventoryLocation, sprintf('INV-%03d', $index));
        }

        $this->assertSame(10, $account->fresh()->machineCount());

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->from(route('machines.create'))
            ->post(route('machines.store'), [
                'location_id' => $inventoryLocation->id,
                'type' => 'snack',
                'serial_number' => 'INV-011',
                'model' => 'Blocked Machine',
                'status' => Machine::STATUS_ACTIVE,
                'installed_on' => '',
            ])
            ->assertRedirect(route('machines.create'))
            ->assertSessionHasErrors([
                'machine_limit' => 'Your Free plan allows up to 10 machines. Upgrade to add more.',
            ]);

        $this->assertDatabaseMissing('tbl_machines', [
            'account_id' => $account->id,
            'serial_number' => 'INV-011',
        ]);

        $account->forceFill(['plan_id' => Plan::STARTER_ID])->save();

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('machines.store'), [
                'location_id' => $inventoryLocation->id,
                'type' => 'snack',
                'serial_number' => 'INV-011',
                'model' => 'Allowed Machine',
                'status' => Machine::STATUS_ACTIVE,
                'installed_on' => '',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tbl_machines', [
            'account_id' => $account->id,
            'serial_number' => 'INV-011',
        ]);
    }

    public function test_bulk_inventory_attachment_moves_existing_machines_even_when_account_is_at_its_limit(): void
    {
        $this->createMachineStatusDictionary();

        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('At Limit Move Account');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);

        $route = $this->createRoute($account, 'Move Route');
        $location = $this->createCustomerLocation($account, $route, 'Move Stop');
        $inventoryLocation = $account->inventoryLocation()->firstOrFail();

        $machineA = $this->createMachine($account, $inventoryLocation, 'MOVE-001');
        $machineB = $this->createMachine($account, $inventoryLocation, 'MOVE-002');

        for ($index = 3; $index <= 10; $index++) {
            $this->createMachine($account, $location, sprintf('MOVE-%03d', $index));
        }

        $this->assertSame(10, $account->fresh()->machineCount());

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('locations.machines.attach.store', $location), [
                'machine_ids' => [$machineA->id, $machineB->id],
                'installation_date' => '2026-07-29',
            ])
            ->assertRedirect(route('locations.show', $location))
            ->assertSessionHas('status');

        $this->assertSame($location->id, $machineA->fresh()->location_id);
        $this->assertSame($location->id, $machineB->fresh()->location_id);
        $this->assertSame(10, $account->fresh()->machineCount());
    }

    public function test_machine_import_preview_marks_over_limit_creates_as_errors(): void
    {
        Storage::fake('private');
        $this->createMachineStatusDictionary();

        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Import Limit Account');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);

        $route = $this->createRoute($account, 'Import Route');
        $location = $this->createCustomerLocation($account, $route, 'Import Stop');
        $inventoryLocation = $account->inventoryLocation()->firstOrFail();

        for ($index = 1; $index <= 10; $index++) {
            $this->createMachine($account, $inventoryLocation, sprintf('CSV-%03d', $index));
        }

        $file = $this->createImportFile(
            ['serial_number', 'type', 'model', 'status', 'installed_on', 'location_name'],
            [
                ['CSV-011', 'snack', 'CSV Blocked', Machine::STATUS_ACTIVE, '2026-07-29', $location->location_name],
            ]
        );

        $response = $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('import-export.import.analyze'), [
                'entity' => 'machines',
                'import_file' => $file,
            ]);

        $response->assertOk();

        $preview = $response->viewData('importPreview');

        $this->assertSame([
            'create' => 0,
            'update' => 0,
            'error' => 1,
            'duplicate_warning' => 0,
        ], $preview['counts']);
        $this->assertSame('error', $preview['rows'][0]['action']);
        $this->assertSame('Your Free plan allows up to 10 machines. Upgrade to add more.', $preview['rows'][0]['message']);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('import-export.import.confirm'), [
                'entity' => 'machines',
                'token' => $preview['token'],
            ])
            ->assertRedirect(route('import-export.index'))
            ->assertSessionHas('status', 'Imported: 0 created, 0 updated.');

        $this->assertDatabaseMissing('tbl_machines', [
            'account_id' => $account->id,
            'serial_number' => 'CSV-011',
        ]);
    }

    public function test_over_limit_account_after_admin_plan_change_can_view_machines_but_cannot_add_more(): void
    {
        $this->createMachineStatusDictionary();

        $superAdmin = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
            'is_super_admin' => true,
        ]);
        $owner = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Over Limit Account');
        $account->forceFill(['plan_id' => Plan::STARTER_ID])->save();
        $this->attachUserToAccount($owner, $account, AccountUser::ROLE_OWNER);

        $inventoryLocation = $account->inventoryLocation()->firstOrFail();

        for ($index = 1; $index <= 11; $index++) {
            $this->createMachine($account, $inventoryLocation, sprintf('DOWN-%03d', $index));
        }

        $this->actingAs($superAdmin)
            ->post(route('admin.accounts.plan.update', $account), [
                'plan_id' => Plan::FREE_ID,
            ])
            ->assertRedirect(route('admin.accounts.index'))
            ->assertSessionHas('status', 'Plan updated to Free.');

        $this->assertSame(Plan::FREE_ID, (int) $account->fresh()->plan_id);

        $this->actingAs($owner)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('machines.index'))
            ->assertOk()
            ->assertSeeText('Over limit by 1 machine');

        $this->actingAs($owner)
            ->withSession(['current_account_id' => $account->id])
            ->from(route('machines.create'))
            ->post(route('machines.store'), [
                'location_id' => $inventoryLocation->id,
                'type' => 'snack',
                'serial_number' => 'DOWN-012',
                'model' => 'Blocked Downshift',
                'status' => Machine::STATUS_ACTIVE,
                'installed_on' => '',
            ])
            ->assertRedirect(route('machines.create'))
            ->assertSessionHasErrors([
                'machine_limit' => 'Your Free plan allows up to 10 machines. You are currently using 11. Upgrade to add more.',
            ]);
    }

    public function test_pro_plan_never_blocks_machine_additions(): void
    {
        $this->createMachineStatusDictionary();

        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Pro Account');
        $account->forceFill(['plan_id' => Plan::PRO_ID])->save();
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);

        $inventoryLocation = $account->inventoryLocation()->firstOrFail();

        for ($index = 1; $index <= 26; $index++) {
            $this->createMachine($account, $inventoryLocation, sprintf('PRO-%03d', $index));
        }

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('machines.store'), [
                'location_id' => $inventoryLocation->id,
                'type' => 'snack',
                'serial_number' => 'PRO-027',
                'model' => 'Unlimited Machine',
                'status' => Machine::STATUS_ACTIVE,
                'installed_on' => '',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tbl_machines', [
            'account_id' => $account->id,
            'serial_number' => 'PRO-027',
        ]);
    }

    public function test_upgrade_request_records_intent_without_processing_payment(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Intent Account');
        $account->forceFill(['plan_id' => Plan::FREE_ID])->save();
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->from(route('machines.index'))
            ->post(route('plan-upgrade-intents.store'), [
                'plan_id' => Plan::STARTER_ID,
                'source' => 'machines_index',
            ])
            ->assertRedirect(route('machines.index'))
            ->assertSessionHas('status', 'Starter upgrade request recorded. Billing is not enabled yet; an admin will follow up manually.');

        $intent = PlanUpgradeRequest::query()->first();

        $this->assertNotNull($intent);
        $this->assertSame($account->id, $intent->account_id);
        $this->assertSame($user->id, $intent->requested_by_user_id);
        $this->assertSame(Plan::FREE_ID, $intent->current_plan_id);
        $this->assertSame(Plan::STARTER_ID, $intent->requested_plan_id);
        $this->assertSame('machines_index', $intent->source);
    }

    protected function createAccount(string $name): Account
    {
        return Account::create([
            'account_name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)).'-'.uniqid(),
            'status' => Account::STATUS_ACTIVE,
            'billing_email' => strtolower(str_replace(' ', '.', $name)).'@example.com',
        ]);
    }

    protected function attachUserToAccount(User $user, Account $account, string $role): void
    {
        AccountUser::create([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => AccountUser::STATUS_ACTIVE,
        ]);
    }

    protected function createMachineStatusDictionary(): void
    {
        DataDictionary::query()->firstOrCreate([
            'account_id' => null,
            'name' => DataDictionary::GROUP_MACHINE_STATUS,
            'value' => Machine::STATUS_ACTIVE,
        ], [
            'label' => 'Active',
            'sort_order' => 1,
            'is_active' => true,
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
            'address' => '123 Main Street',
            'city' => 'New York',
            'state' => 'NY',
            'zip_code' => '10001',
            'is_inventory' => null,
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

    protected function createMachine(Account $account, Location $location, string $serialNumber): Machine
    {
        return Machine::create([
            'account_id' => $account->id,
            'location_id' => $location->id,
            'type' => 'snack',
            'serial_number' => $serialNumber,
            'model' => 'Model '.$serialNumber,
            'status' => Machine::STATUS_ACTIVE,
            'installed_on' => null,
        ]);
    }

    protected function createImportFile(array $headers, array $rows): UploadedFile
    {
        $stream = fopen('php://temp', 'w+');
        fputcsv($stream, $headers);

        foreach ($rows as $row) {
            fputcsv($stream, $row);
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return UploadedFile::fake()->createWithContent('import.csv', $csv ?: '');
    }
}
