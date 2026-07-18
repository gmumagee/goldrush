<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\Bin;
use App\Models\CalendarEvent;
use App\Models\CalendarReminder;
use App\Models\DataDictionary;
use App\Models\Location;
use App\Models\Machine;
use App\Models\Product;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\User;
use App\Models\VendingRoute;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_location_service_can_be_created_opened_worked_and_closed(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $account = $this->createAccount('Alpha Vending');
        $this->attachUserToAccount($user, $account, 'owner');
        $this->seedServiceTypes();

        $route = $this->createRoute($account, 'North Route');
        $location = $this->createLocation($account, $route, 'Campus Center');
        $warehouse = $this->createWarehouse($account, 'Main Warehouse');

        $machineOne = $this->createMachine($account, $location, 'Snack');
        $machineTwo = $this->createMachine($account, $location, 'Soda');

        $productOne = $this->createProduct($account, 'Chips');
        $productTwo = $this->createProduct($account, 'Cola');

        $binOne = $this->createBin($account, $machineOne, $productOne, 'A1', 10, 1.50);
        $binTwo = $this->createBin($account, $machineTwo, $productTwo, 'B1', 12, 2.25);

        $response = $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('services.store'), [
                'location_id' => $location->id,
                'warehouse_id' => $warehouse->id,
                'service_type' => Service::TYPE_LOCATION_SERVICE,
                'service_date' => '2026-07-10',
                'user_id' => $user->id,
            ]);

        $service = Service::query()->firstOrFail();

        $response->assertRedirect(route('services.show', $service->id));
        $this->assertSame(Service::STATUS_AWAITING_SERVICE, $service->status);
        $this->assertSame($location->id, $service->location_id);
        $this->assertNull($service->opened_at);
        $this->assertNull($service->closed_at);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('services.open', $service->id))
            ->assertRedirect(route('services.show', $service->id));

        $service->refresh();
        $this->assertSame(Service::STATUS_SERVICE_OPEN, $service->status);
        $this->assertNotNull($service->opened_at);
        $this->assertNull($service->closed_at);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('services.show', $service->id))
            ->assertOk()
            ->assertSee('Snack')
            ->assertSee('Soda');

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('services.machines.count.store', [$service->id, $machineOne->id]), [
                'quantities' => [
                    $binOne->id => 4,
                ],
            ])
            ->assertRedirect(route('services.show', $service->id));

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('services.machines.fill.store', [$service->id, $machineOne->id]), [
                'quantities' => [
                    $binOne->id => 3,
                ],
            ])
            ->assertRedirect(route('services.show', $service->id));

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('services.machines.count.store', [$service->id, $machineTwo->id]), [
                'quantities' => [
                    $binTwo->id => 6,
                ],
            ])
            ->assertRedirect(route('services.show', $service->id));

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('services.machines.fill.store', [$service->id, $machineTwo->id]), [
                'quantities' => [
                    $binTwo->id => 2,
                ],
            ])
            ->assertRedirect(route('services.show', $service->id));

        $this->assertDatabaseHas('tbl_transactions', [
            'service_id' => $service->id,
            'machine_id' => $machineOne->id,
            'bin_id' => $binOne->id,
            'transaction_type' => 'count',
            'quantity' => 4,
            'product_id' => $productOne->id,
        ]);

        $this->assertDatabaseHas('tbl_transactions', [
            'service_id' => $service->id,
            'machine_id' => $machineTwo->id,
            'bin_id' => $binTwo->id,
            'transaction_type' => 'fill',
            'quantity' => 2,
            'product_id' => $productTwo->id,
        ]);

        $this->assertSame(4, Transaction::query()->count());
        $this->assertSame(4, Transaction::query()->whereNotNull('transaction_at')->count());

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('services.complete', $service->id))
            ->assertRedirect(route('services.show', $service->id));

        $service->refresh();
        $this->assertSame(Service::STATUS_SERVICE_COMPLETED, $service->status);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('services.amount-collected.update', $service->id), [
                'amount_collected' => 123.45,
            ])
            ->assertRedirect(route('services.show', $service->id));

        $service->refresh();
        $this->assertSame(Service::STATUS_SERVICE_CLOSED, $service->status);
        $this->assertNotNull($service->closed_at);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->from(route('services.show', $service->id))
            ->post(route('services.machines.fill.store', [$service->id, $machineOne->id]), [
                'quantities' => [
                    $binOne->id => 1,
                ],
            ])
            ->assertRedirect(route('services.show', $service->id))
            ->assertSessionHasErrors('service');

        $this->assertSame(4, Transaction::query()->count());
    }

    public function test_maintenance_service_can_be_created_opened_closed_and_cannot_use_inventory_or_amount_collected(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $account = $this->createAccount('Maintenance Account');
        $this->attachUserToAccount($user, $account, 'owner');
        $this->seedServiceTypes();

        $route = $this->createRoute($account, 'Maintenance Route');
        $location = $this->createLocation($account, $route, 'Main Office');
        $machine = $this->createMachine($account, $location, 'Snack');
        $product = $this->createProduct($account, 'Trail Mix');
        $bin = $this->createBin($account, $machine, $product, 'A1', 10, 2.50);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('services.store'), [
                'location_id' => $location->id,
                'service_type' => Service::TYPE_MAINTENANCE,
                'service_date' => '2026-07-18',
                'user_id' => $user->id,
                'notes' => 'Inspect cooling fan.',
            ])
            ->assertRedirect();

        $service = Service::query()->firstOrFail();
        $calendarEvent = CalendarEvent::query()
            ->where('account_id', $account->id)
            ->where('source_type', CalendarEvent::SOURCE_TYPE_SERVICE)
            ->where('source_id', $service->id)
            ->firstOrFail();

        $this->assertTrue($service->isMaintenanceService());
        $this->assertNull($service->warehouse_id);
        $this->assertSame(Service::STATUS_AWAITING_SERVICE, $service->status);
        $this->assertSame('Inspect cooling fan.', $service->notes);
        $this->assertSame('Maintenance', $calendarEvent->event_type);
        $this->assertSame('Maintenance: Main Office', $calendarEvent->title);

        CalendarReminder::create([
            'account_id' => $account->id,
            'calendar_event_id' => $calendarEvent->id,
            'remind_at' => '2026-07-18 07:30:00',
            'reminder_type' => CalendarReminder::TYPE_DASHBOARD,
            'status' => CalendarReminder::STATUS_PENDING,
            'assigned_user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('services.maintenance.open', $service->id))
            ->assertRedirect(route('services.show', $service->id));

        $service->refresh();
        $this->assertSame(Service::STATUS_SERVICE_OPEN, $service->status);
        $this->assertNotNull($service->opened_at);
        $this->assertNull($service->completed_at);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('services.show', $service->id))
            ->assertOk()
            ->assertSeeText('Maintenance Service')
            ->assertSeeText('Maintenance Notes')
            ->assertSeeText('Close Maintenance Service')
            ->assertDontSeeText('Complete Service')
            ->assertDontSeeText('Enter Amount Collected')
            ->assertDontSeeText('Count Machine')
            ->assertDontSeeText('Fill Machine');

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->from(route('services.show', $service->id))
            ->post(route('services.complete', $service->id))
            ->assertRedirect(route('services.show', $service->id))
            ->assertSessionHasErrors('service');

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->from(route('services.show', $service->id))
            ->get(route('services.amount-collected.edit', $service->id))
            ->assertRedirect(route('services.show', $service->id))
            ->assertSessionHasErrors('service');

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->from(route('services.show', $service->id))
            ->post(route('services.machines.count.store', [$service->id, $machine->id]), [
                'quantities' => [
                    $bin->id => 4,
                ],
            ])
            ->assertRedirect(route('services.show', $service->id))
            ->assertSessionHasErrors('service');

        $this->assertSame(0, Transaction::query()->count());

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->put(route('services.maintenance.close', $service->id), [
                'notes' => 'Replaced fan and tested airflow.',
            ])
            ->assertRedirect(route('services.show', $service->id));

        $service->refresh();
        $calendarEvent->refresh();
        $reminder = CalendarReminder::query()->where('calendar_event_id', $calendarEvent->id)->firstOrFail();

        $this->assertSame(Service::STATUS_SERVICE_CLOSED, $service->status);
        $this->assertSame('Replaced fan and tested airflow.', $service->notes);
        $this->assertNull($service->completed_at);
        $this->assertNull($service->amount_collected);
        $this->assertNotNull($service->closed_at);
        $this->assertSame(CalendarEvent::STATUS_COMPLETED, $calendarEvent->status);
        $this->assertSame('Maintenance', $calendarEvent->event_type);
        $this->assertNotNull($calendarEvent->completed_at);
        $this->assertSame(CalendarReminder::STATUS_DISMISSED, $reminder->status);
        $this->assertSame($user->id, $reminder->dismissed_by_user_id);
    }

    public function test_submitted_status_is_ignored_when_creating_a_service(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $account = $this->createAccount('Tamper Account');
        $this->attachUserToAccount($user, $account, 'owner');
        $this->seedServiceTypes();

        $route = $this->createRoute($account, 'Tamper Route');
        $location = $this->createLocation($account, $route, 'Main Office');

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('services.store'), [
                'location_id' => $location->id,
                'service_type' => Service::TYPE_MAINTENANCE,
                'service_date' => '2026-07-18',
                'status' => Service::STATUS_CLOSED,
            ])
            ->assertRedirect();

        $service = Service::query()->firstOrFail();
        $calendarEvent = CalendarEvent::query()
            ->where('account_id', $account->id)
            ->where('source_type', CalendarEvent::SOURCE_TYPE_SERVICE)
            ->where('source_id', $service->id)
            ->firstOrFail();

        $this->assertSame(Service::TYPE_MAINTENANCE, $service->service_type);
        $this->assertSame(Service::STATUS_AWAITING, $service->status);
        $this->assertNull($service->opened_at);
        $this->assertNull($service->completed_at);
        $this->assertNull($service->closed_at);
        $this->assertNull($service->closed_by_user_id);
        $this->assertNull($service->amount_collected);
        $this->assertSame(CalendarEvent::STATUS_SCHEDULED, $calendarEvent->status);
    }

    public function test_service_routes_are_isolated_to_the_current_account(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $accountA = $this->createAccount('Account A');
        $accountB = $this->createAccount('Account B');
        $this->attachUserToAccount($user, $accountA, 'owner');

        $route = $this->createRoute($accountB, 'South Route');
        $location = $this->createLocation($accountB, $route, 'Remote Stop');
        $machine = $this->createMachine($accountB, $location, 'Snack');
        $service = Service::create([
            'account_id' => $accountB->id,
            'location_id' => $location->id,
            'user_id' => null,
            'service_type' => Service::TYPE_LOCATION_SERVICE,
            'service_date' => '2026-07-10',
            'opened_at' => null,
            'closed_at' => null,
            'status' => Service::STATUS_AWAITING_SERVICE,
        ]);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $accountA->id])
            ->get(route('services.show', $service->id))
            ->assertNotFound();

        $this->actingAs($user)
            ->withSession(['current_account_id' => $accountA->id])
            ->get(route('services.machines.count', [$service->id, $machine->id]))
            ->assertNotFound();
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

    protected function createLocation(Account $account, VendingRoute $route, string $name): Location
    {
        return Location::create([
            'account_id' => $account->id,
            'route_id' => $route->id,
            'location_name' => $name,
            'address' => '123 Service Road',
            'city' => 'Toronto',
            'state' => 'ON',
            'zip_code' => 'M1M1M1',
            'contact_name' => 'Casey Tech',
        ]);
    }

    protected function createMachine(Account $account, Location $location, string $type): Machine
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

    protected function createWarehouse(Account $account, string $name): Warehouse
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

    protected function createProduct(Account $account, string $name): Product
    {
        return Product::create([
            'account_id' => $account->id,
            'vendor_id' => null,
            'sku' => strtolower($name).'-'.uniqid(),
            'product_name' => $name,
            'barcode' => uniqid(),
        ]);
    }

    protected function createBin(Account $account, Machine $machine, Product $product, string $code, int $capacity, float $price): Bin
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

    protected function seedServiceTypes(): void
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
}
