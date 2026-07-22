<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\Bin;
use App\Models\CalendarEvent;
use App\Models\DataDictionary;
use App\Models\Location;
use App\Models\Machine;
use App\Models\Product;
use App\Models\RouteLocation;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\User;
use App\Models\VendingRoute;
use App\Models\Warehouse;
use App\Services\CalendarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_sets_created_by_user_id_to_the_actual_creator_even_when_a_different_assignee_is_submitted(): void
    {
        $creator = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $assignee = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Creator Tracking Account');

        $this->attachUserToAccount($creator, $account, AccountUser::ROLE_MANAGER);
        $this->attachUserToAccount($assignee, $account, AccountUser::ROLE_TECHNICIAN);
        $this->seedServiceTypes();

        $route = $this->createRoute($account, 'Creator Route');
        $location = $this->createLocation($account, $route, 'Creator Stop');
        $warehouse = $this->createWarehouse($account, 'Creator Warehouse');

        $this->actingAs($creator)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('services.store'), [
                'location_id' => $location->id,
                'warehouse_id' => $warehouse->id,
                'service_type' => Service::TYPE_LOCATION,
                'service_date' => '2026-07-22',
                'user_id' => $assignee->id,
            ])
            ->assertRedirect();

        $service = Service::query()->latest('id')->firstOrFail();

        $this->assertSame($creator->id, $service->created_by_user_id);
        $this->assertSame($assignee->id, $service->user_id);
    }

    public function test_creator_can_delete_their_own_empty_service_after_password_confirmation_and_calendar_event_is_removed(): void
    {
        $creator = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Creator Delete Account');

        $this->attachUserToAccount($creator, $account, AccountUser::ROLE_MANAGER);

        $route = $this->createRoute($account, 'Delete Route');
        $location = $this->createLocation($account, $route, 'Delete Stop');
        $warehouse = $this->createWarehouse($account, 'Delete Warehouse');
        $service = $this->createService($account, $location, $warehouse, [
            'user_id' => $creator->id,
            'created_by_user_id' => $creator->id,
            'service_type' => Service::TYPE_LOCATION,
        ]);

        $calendarEvent = app(CalendarService::class)->createServiceEvent($service, $creator->id);

        $this->actingAs($creator)
            ->withSession(['current_account_id' => $account->id])
            ->from(route('services.show', $service))
            ->delete(route('services.destroy', $service))
            ->assertRedirect(route('password.confirm'));

        $this->post(route('password.confirm.store'), [
            'password' => 'password',
        ])->assertRedirect(route('services.show', $service))
            ->assertSessionHas('auth.password_confirmed_at');

        $this->withSession([
            'current_account_id' => $account->id,
            'auth.password_confirmed_at' => now()->unix(),
        ])->delete(route('services.destroy', $service))
            ->assertRedirect(route('services.index'));

        $this->assertDatabaseMissing('tbl_services', ['id' => $service->id]);
        $this->assertDatabaseMissing('tbl_calendar_events', ['id' => $calendarEvent->id]);
    }

    public function test_admin_tier_users_can_delete_any_empty_service_in_their_account(): void
    {
        $account = $this->createAccount('Admin Delete Account');
        $creator = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $admin = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $owner = User::factory()->create(['status' => User::STATUS_ACTIVE]);

        $this->attachUserToAccount($creator, $account, AccountUser::ROLE_MANAGER);
        $this->attachUserToAccount($admin, $account, AccountUser::ROLE_ADMIN);
        $this->attachUserToAccount($owner, $account, AccountUser::ROLE_OWNER);

        $route = $this->createRoute($account, 'Admin Route');
        $location = $this->createLocation($account, $route, 'Admin Stop');
        $warehouse = $this->createWarehouse($account, 'Admin Warehouse');

        $adminService = $this->createService($account, $location, $warehouse, [
            'user_id' => $creator->id,
            'created_by_user_id' => $creator->id,
        ]);

        $ownerService = $this->createService($account, $location, $warehouse, [
            'user_id' => $creator->id,
            'created_by_user_id' => $creator->id,
            'service_date' => '2026-07-23',
        ]);

        $this->actingAs($admin)
            ->withSession([
                'current_account_id' => $account->id,
                'auth.password_confirmed_at' => now()->unix(),
            ])
            ->delete(route('services.destroy', $adminService))
            ->assertRedirect(route('services.index'));

        $this->assertDatabaseMissing('tbl_services', ['id' => $adminService->id]);

        $this->actingAs($owner)
            ->withSession([
                'current_account_id' => $account->id,
                'auth.password_confirmed_at' => now()->unix(),
            ])
            ->delete(route('services.destroy', $ownerService))
            ->assertRedirect(route('services.index'));

        $this->assertDatabaseMissing('tbl_services', ['id' => $ownerService->id]);
    }

    public function test_non_admin_non_creator_cannot_delete_a_service(): void
    {
        $account = $this->createAccount('Forbidden Delete Account');
        $creator = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $technician = User::factory()->create(['status' => User::STATUS_ACTIVE]);

        $this->attachUserToAccount($creator, $account, AccountUser::ROLE_MANAGER);
        $this->attachUserToAccount($technician, $account, AccountUser::ROLE_TECHNICIAN);

        $route = $this->createRoute($account, 'Forbidden Route');
        $location = $this->createLocation($account, $route, 'Forbidden Stop');
        $warehouse = $this->createWarehouse($account, 'Forbidden Warehouse');
        $service = $this->createService($account, $location, $warehouse, [
            'user_id' => $creator->id,
            'created_by_user_id' => $creator->id,
        ]);

        $this->actingAs($technician)
            ->withSession([
                'current_account_id' => $account->id,
                'auth.password_confirmed_at' => now()->unix(),
            ])
            ->delete(route('services.destroy', $service))
            ->assertForbidden();
    }

    public function test_deleting_without_a_confirmed_password_is_blocked(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Password Gate Account');

        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);

        $route = $this->createRoute($account, 'Password Route');
        $location = $this->createLocation($account, $route, 'Password Stop');
        $warehouse = $this->createWarehouse($account, 'Password Warehouse');
        $service = $this->createService($account, $location, $warehouse, [
            'user_id' => $user->id,
            'created_by_user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->from(route('services.show', $service))
            ->delete(route('services.destroy', $service))
            ->assertRedirect(route('password.confirm'));

        $this->assertDatabaseHas('tbl_services', ['id' => $service->id]);
    }

    public function test_services_with_transactions_cannot_be_deleted_even_by_admins(): void
    {
        $owner = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Transaction Guard Account');

        $this->attachUserToAccount($owner, $account, AccountUser::ROLE_OWNER);
        $this->seedServiceTypes();

        $route = $this->createRoute($account, 'Transaction Route');
        $location = $this->createLocation($account, $route, 'Transaction Stop');
        $warehouse = $this->createWarehouse($account, 'Transaction Warehouse');
        $machine = $this->createMachine($account, $location, 'Snack');
        $product = $this->createProduct($account, 'Guard Product');
        $bin = $this->createBin($account, $machine, $product, 'A1', 10, 1.50);
        $service = $this->createService($account, $location, $warehouse, [
            'user_id' => $owner->id,
            'created_by_user_id' => $owner->id,
        ]);

        $this->createTransaction($account, $service, $bin, Transaction::TYPE_COUNT, 4, '2026-07-22 09:30:00');

        $this->actingAs($owner)
            ->withSession([
                'current_account_id' => $account->id,
                'auth.password_confirmed_at' => now()->unix(),
            ])
            ->from(route('services.show', $service))
            ->delete(route('services.destroy', $service))
            ->assertRedirect(route('services.show', $service))
            ->assertSessionHasErrors('service');

        $this->assertDatabaseHas('tbl_services', ['id' => $service->id]);
    }

    public function test_delete_button_only_renders_for_authorized_users_and_hides_when_transactions_exist(): void
    {
        $creator = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $owner = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $viewer = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Delete Button Account');

        $this->attachUserToAccount($creator, $account, AccountUser::ROLE_MANAGER);
        $this->attachUserToAccount($owner, $account, AccountUser::ROLE_OWNER);
        $this->attachUserToAccount($viewer, $account, AccountUser::ROLE_VIEWER);
        $this->seedServiceTypes();

        $route = $this->createRoute($account, 'Button Route');
        $location = $this->createLocation($account, $route, 'Button Stop');
        $warehouse = $this->createWarehouse($account, 'Button Warehouse');
        $machine = $this->createMachine($account, $location, 'Snack');
        $product = $this->createProduct($account, 'Button Product');
        $bin = $this->createBin($account, $machine, $product, 'A1', 10, 1.75);
        $service = $this->createService($account, $location, $warehouse, [
            'user_id' => $creator->id,
            'created_by_user_id' => $creator->id,
        ]);

        $this->actingAs($creator)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('services.show', $service))
            ->assertOk()
            ->assertSeeText('Delete Service')
            ->assertDontSeeText('Service has transactions and cannot be deleted');

        $this->actingAs($viewer)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('services.show', $service))
            ->assertOk()
            ->assertDontSeeText('Delete Service')
            ->assertDontSeeText('Service has transactions and cannot be deleted');

        $this->createTransaction($account, $service, $bin, Transaction::TYPE_COUNT, 2, '2026-07-22 11:00:00');

        $this->actingAs($owner)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('services.show', $service))
            ->assertOk()
            ->assertDontSeeText('Delete Service')
            ->assertSeeText('Service has transactions and cannot be deleted');
    }

    private function createAccount(string $name): Account
    {
        return Account::create([
            'account_name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)).'-'.uniqid(),
            'status' => Account::STATUS_ACTIVE,
            'billing_email' => strtolower(str_replace(' ', '.', $name)).'@example.com',
        ]);
    }

    private function attachUserToAccount(User $user, Account $account, string $role): void
    {
        AccountUser::create([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => AccountUser::STATUS_ACTIVE,
        ]);
    }

    private function seedServiceTypes(): void
    {
        DataDictionary::updateOrCreate(
            [
                'account_id' => null,
                'name' => DataDictionary::GROUP_SERVICE_TYPE,
                'value' => Service::TYPE_LOCATION,
            ],
            [
                'label' => 'Location Service',
                'sort_order' => 10,
                'is_active' => true,
            ],
        );

        DataDictionary::updateOrCreate(
            [
                'account_id' => null,
                'name' => DataDictionary::GROUP_SERVICE_TYPE,
                'value' => Service::TYPE_MAINTENANCE,
            ],
            [
                'label' => 'Maintenance Service',
                'sort_order' => 20,
                'is_active' => true,
            ],
        );
    }

    private function createRoute(Account $account, string $name): VendingRoute
    {
        return VendingRoute::create([
            'account_id' => $account->id,
            'route_name' => $name,
            'description' => $name.' description',
        ]);
    }

    private function createLocation(Account $account, VendingRoute $route, string $name): Location
    {
        $location = Location::create([
            'account_id' => $account->id,
            'location_name' => $name,
            'address' => '123 Service Road',
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

    private function createWarehouse(Account $account, string $name): Warehouse
    {
        return Warehouse::create([
            'account_id' => $account->id,
            'warehouse_name' => $name,
            'address' => '10 Storage Way',
            'city' => 'Toronto',
            'state' => 'ON',
            'zip_code' => 'M1M1M1',
        ]);
    }

    private function createMachine(Account $account, Location $location, string $type): Machine
    {
        return Machine::create([
            'account_id' => $account->id,
            'location_id' => $location->id,
            'type' => $type,
            'serial_number' => $type.'-'.uniqid(),
            'model' => $type.' Model',
            'status' => 'active',
            'installed_on' => '2026-07-01',
        ]);
    }

    private function createProduct(Account $account, string $name): Product
    {
        return Product::create([
            'account_id' => $account->id,
            'vendor_id' => null,
            'sku' => strtolower(str_replace(' ', '-', $name)).'-'.uniqid(),
            'product_name' => $name,
            'barcode' => uniqid(),
        ]);
    }

    private function createBin(Account $account, Machine $machine, Product $product, string $code, int $capacity, float $price): Bin
    {
        return Bin::create([
            'account_id' => $account->id,
            'machine_id' => $machine->id,
            'product_id' => $product->id,
            'bin_code' => $code,
            'capacity' => $capacity,
            'price' => $price,
        ]);
    }

    private function createService(Account $account, Location $location, ?Warehouse $warehouse, array $attributes = []): Service
    {
        return Service::create(array_merge([
            'account_id' => $account->id,
            'location_id' => $location->id,
            'warehouse_id' => $warehouse?->id,
            'user_id' => null,
            'created_by_user_id' => null,
            'closed_by_user_id' => null,
            'service_type' => Service::TYPE_LOCATION,
            'notes' => null,
            'service_date' => '2026-07-22',
            'scheduled_at' => '2026-07-22 00:00:00',
            'opened_at' => null,
            'completed_at' => null,
            'closed_at' => null,
            'amount_collected' => null,
            'status' => Service::STATUS_AWAITING,
        ], $attributes));
    }

    private function createTransaction(
        Account $account,
        Service $service,
        Bin $bin,
        string $type,
        int $quantity,
        string $transactionAt,
        int $spoilage = 0,
    ): Transaction {
        return Transaction::create([
            'account_id' => $account->id,
            'service_id' => $service->id,
            'machine_id' => $bin->machine_id,
            'bin_id' => $bin->id,
            'product_id' => $bin->product_id,
            'transaction_type' => $type,
            'quantity' => $quantity,
            'spoilage' => $spoilage,
            'transaction_at' => $transactionAt,
            'price' => $bin->price,
            'unit_cost' => null,
        ]);
    }
}
