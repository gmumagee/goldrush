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
use App\Models\ServiceSale;
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

        $previousService = Service::create([
            'account_id' => $account->id,
            'location_id' => $location->id,
            'warehouse_id' => $warehouse->id,
            'user_id' => $user->id,
            'service_type' => Service::TYPE_LOCATION_SERVICE,
            'service_date' => '2026-07-09',
            'opened_at' => '2026-07-09 08:00:00',
            'completed_at' => '2026-07-09 10:00:00',
            'closed_at' => '2026-07-09 10:30:00',
            'amount_collected' => 0,
            'status' => Service::STATUS_SERVICE_CLOSED,
        ]);

        $this->createTransaction($account, $previousService, $binOne, Transaction::TYPE_CURRENT_INVENTORY, 20, '2026-07-09 09:00:00');
        $this->createTransaction($account, $previousService, $binTwo, Transaction::TYPE_CURRENT_INVENTORY, 12, '2026-07-09 09:15:00');

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
            ->get(route('services.machines.count', [$service->id, $machineOne->id]))
            ->assertOk()
            ->assertSeeInOrder(['Price', 'Spoilage', 'Count'])
            ->assertSee('data-bs-toggle="tooltip"', false)
            ->assertSee('aria-label="About Spoilage"', false)
            ->assertSee('aria-label="About Count"', false)
            ->assertSee('type="button"', false)
            ->assertSee('title="Enter expired, damaged, or otherwise unsellable products removed from the bin."', false)
            ->assertSee('title="Enter only usable, saleable products remaining after spoilage has been removed."', false)
            ->assertSeeInOrder([
                'name="counts['.$binOne->id.'][spoilage]"',
                'name="counts['.$binOne->id.'][quantity]"',
            ], false)
            ->assertSee('name="counts['.$binOne->id.'][quantity]"', false)
            ->assertSee('name="counts['.$binOne->id.'][spoilage]"', false)
            ->assertDontSeeText('Count only usable, saleable products remaining in the bin. Enter expired or damaged products separately under Spoilage.')
            ->assertDontSeeText('Usable products remaining after spoilage is removed.')
            ->assertDontSeeText('Expired, damaged, or unsellable products removed from this bin.');

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('services.machines.count.store', [$service->id, $machineOne->id]), [
                'counts' => [
                    $binOne->id => [
                        'quantity' => 4,
                        'spoilage' => 1,
                    ],
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
                'counts' => [
                    $binTwo->id => [
                        'quantity' => 6,
                        'spoilage' => 2,
                    ],
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
            'transaction_type' => Transaction::TYPE_COUNT,
            'quantity' => 4,
            'spoilage' => 1,
            'product_id' => $productOne->id,
        ]);

        $this->assertDatabaseHas('tbl_transactions', [
            'service_id' => $service->id,
            'machine_id' => $machineTwo->id,
            'bin_id' => $binTwo->id,
            'transaction_type' => Transaction::TYPE_FILL,
            'quantity' => 2,
            'product_id' => $productTwo->id,
        ]);

        $this->assertSame(6, Transaction::query()->count());
        $this->assertSame(6, Transaction::query()->whereNotNull('transaction_at')->count());

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('services.complete', $service->id))
            ->assertRedirect(route('services.show', $service->id));

        $service->refresh();
        $this->assertSame(Service::STATUS_SERVICE_COMPLETED, $service->status);
        $this->assertDatabaseCount('tbl_service_sales', 2);
        $this->assertDatabaseHas('tbl_service_sales', [
            'service_id' => $service->id,
            'bin_id' => $binOne->id,
            'product_id' => $productOne->id,
            'calculation_status' => ServiceSale::CALCULATION_CALCULATED,
            'opening_quantity' => 20,
            'spoilage' => 1,
            'counted_quantity' => 4,
            'units_sold' => 15,
            'unit_price' => 1.50,
            'sales_amount' => 22.50,
        ]);
        $this->assertDatabaseHas('tbl_service_sales', [
            'service_id' => $service->id,
            'bin_id' => $binTwo->id,
            'product_id' => $productTwo->id,
            'calculation_status' => ServiceSale::CALCULATION_CALCULATED,
            'opening_quantity' => 12,
            'spoilage' => 2,
            'counted_quantity' => 6,
            'units_sold' => 4,
            'unit_price' => 2.25,
            'sales_amount' => 9.00,
        ]);
        $this->assertDatabaseHas('tbl_transactions', [
            'service_id' => $service->id,
            'bin_id' => $binOne->id,
            'product_id' => $productOne->id,
            'transaction_type' => Transaction::TYPE_CURRENT_INVENTORY,
            'quantity' => 7,
        ]);
        $this->assertDatabaseHas('tbl_transactions', [
            'service_id' => $service->id,
            'bin_id' => $binTwo->id,
            'product_id' => $productTwo->id,
            'transaction_type' => Transaction::TYPE_CURRENT_INVENTORY,
            'quantity' => 8,
        ]);
        $this->assertDatabaseMissing('tbl_transactions', [
            'service_id' => $service->id,
            'transaction_type' => 'sale',
        ]);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('services.show', $service->id))
            ->assertOk()
            ->assertSeeText('Sales Breakdown')
            ->assertSeeText('Machine Total')
            ->assertSeeText('15 units sold')
            ->assertSeeText('4 units sold')
            ->assertSeeText('$22.50')
            ->assertSeeText('$9.00')
            ->assertSeeText('Spoilage')
            ->assertDontSeeText('Additions')
            ->assertDontSeeText('Removals')
            ->assertSeeText('Spoilage: 1')
            ->assertViewHas('machineSalesGroups', function ($groups) use ($machineOne, $machineTwo, $binOne, $binTwo) {
                $machineGroups = $groups->keyBy(fn (array $group) => (int) ($group['machine']?->id ?? 0));

                return $machineGroups->count() === 2
                    && $machineGroups->has($machineOne->id)
                    && $machineGroups->has($machineTwo->id)
                    && $machineGroups[$machineOne->id]['sales']->pluck('bin_id')->all() === [$binOne->id]
                    && $machineGroups[$machineTwo->id]['sales']->pluck('bin_id')->all() === [$binTwo->id]
                    && $machineGroups[$machineOne->id]['total_units_sold'] === 15
                    && $machineGroups[$machineTwo->id]['total_units_sold'] === 4
                    && $machineGroups[$machineOne->id]['total_sales'] === '22.50'
                    && $machineGroups[$machineTwo->id]['total_sales'] === '9.00';
            });

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

        $this->assertSame(8, Transaction::query()->count());
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
            ->assertDontSeeText('Sales Breakdown')
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
                'counts' => [
                    $bin->id => [
                        'quantity' => 4,
                        'spoilage' => 1,
                    ],
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
        $this->assertDatabaseCount('tbl_service_sales', 0);
        $this->assertSame(CalendarEvent::STATUS_COMPLETED, $calendarEvent->status);
        $this->assertSame('Maintenance', $calendarEvent->event_type);
        $this->assertNotNull($calendarEvent->completed_at);
        $this->assertSame(CalendarReminder::STATUS_DISMISSED, $reminder->status);
        $this->assertSame($user->id, $reminder->dismissed_by_user_id);
    }

    public function test_first_service_completes_with_a_baseline_line_and_creates_a_current_inventory_snapshot(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $account = $this->createAccount('Reconciliation Account');
        $this->attachUserToAccount($user, $account, 'owner');
        $this->seedServiceTypes();

        $route = $this->createRoute($account, 'North Route');
        $location = $this->createLocation($account, $route, 'Campus Center');
        $warehouse = $this->createWarehouse($account, 'Main Warehouse');
        $machine = $this->createMachine($account, $location, 'Snack');
        $product = $this->createProduct($account, 'Chips');
        $bin = $this->createBin($account, $machine, $product, 'A1', 10, 1.50);

        $service = Service::create([
            'account_id' => $account->id,
            'location_id' => $location->id,
            'warehouse_id' => $warehouse->id,
            'user_id' => $user->id,
            'service_type' => Service::TYPE_LOCATION_SERVICE,
            'service_date' => '2026-07-18',
            'opened_at' => '2026-07-18 08:00:00',
            'closed_at' => null,
            'status' => Service::STATUS_SERVICE_OPEN,
        ]);

        $this->createTransaction($account, $service, $bin, Transaction::TYPE_COUNT, 4, '2026-07-18 09:00:00', 2);
        $this->createTransaction($account, $service, $bin, Transaction::TYPE_FILL, 3, '2026-07-18 09:15:00');

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('services.complete', $service->id))
            ->assertRedirect(route('services.show', $service->id));

        $service->refresh();
        $this->assertSame(Service::STATUS_SERVICE_COMPLETED, $service->status);
        $this->assertDatabaseHas('tbl_service_sales', [
            'service_id' => $service->id,
            'bin_id' => $bin->id,
            'product_id' => $product->id,
            'calculation_status' => ServiceSale::CALCULATION_BASELINE,
            'opening_quantity' => null,
            'spoilage' => 2,
            'counted_quantity' => 4,
            'units_sold' => null,
            'sales_amount' => null,
        ]);
        $this->assertDatabaseHas('tbl_transactions', [
            'service_id' => $service->id,
            'bin_id' => $bin->id,
            'product_id' => $product->id,
            'transaction_type' => Transaction::TYPE_CURRENT_INVENTORY,
            'quantity' => 7,
        ]);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('services.show', $service->id))
            ->assertOk()
            ->assertSeeText('Sales Breakdown')
            ->assertSeeText('Initial Installation — sales will be available after the next service.')
            ->assertSeeText('Initial Installation')
            ->assertSeeText('Some bins are being recorded as an initial installation because no previous inventory snapshot exists. Sales for those bins will be available after the next service.')
            ->assertDontSeeText('Baseline')
            ->assertViewHas('machineSalesGroups', function ($groups) use ($machine, $bin) {
                $machineGroup = $groups->firstWhere('machine.id', $machine->id);

                return $machineGroup !== null
                    && $machineGroup['sales']->pluck('bin_id')->all() === [$bin->id]
                    && $machineGroup['calculated_count'] === 0
                    && $machineGroup['baseline_count'] === 1
                    && $machineGroup['total_units_sold'] === 0
                    && $machineGroup['total_sales'] === null;
            });

        $nextService = Service::create([
            'account_id' => $account->id,
            'location_id' => $location->id,
            'warehouse_id' => $warehouse->id,
            'user_id' => $user->id,
            'service_type' => Service::TYPE_LOCATION_SERVICE,
            'service_date' => '2026-07-19',
            'opened_at' => '2026-07-19 08:00:00',
            'closed_at' => null,
            'status' => Service::STATUS_SERVICE_OPEN,
        ]);

        $this->createTransaction($account, $nextService, $bin, Transaction::TYPE_COUNT, 2, '2026-07-19 09:00:00', 1);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('services.complete', $nextService->id))
            ->assertRedirect(route('services.show', $nextService->id));

        $this->assertDatabaseHas('tbl_service_sales', [
            'service_id' => $nextService->id,
            'bin_id' => $bin->id,
            'product_id' => $product->id,
            'calculation_status' => ServiceSale::CALCULATION_CALCULATED,
            'opening_quantity' => 7,
            'spoilage' => 1,
            'counted_quantity' => 2,
            'units_sold' => 4,
            'sales_amount' => 6.00,
        ]);
    }

    public function test_count_updates_existing_spoilage_value_instead_of_creating_duplicate_count_rows(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $account = $this->createAccount('Spoilage Update Account');
        $this->attachUserToAccount($user, $account, 'owner');
        $this->seedServiceTypes();

        $route = $this->createRoute($account, 'Spoilage Route');
        $location = $this->createLocation($account, $route, 'Spoilage Stop');
        $warehouse = $this->createWarehouse($account, 'Spoilage Warehouse');
        $machine = $this->createMachine($account, $location, 'Snack');
        $product = $this->createProduct($account, 'Cookies');
        $bin = $this->createBin($account, $machine, $product, 'A1', 10, 1.25);

        $service = Service::create([
            'account_id' => $account->id,
            'location_id' => $location->id,
            'warehouse_id' => $warehouse->id,
            'user_id' => $user->id,
            'service_type' => Service::TYPE_LOCATION_SERVICE,
            'service_date' => '2026-07-19',
            'opened_at' => '2026-07-19 08:00:00',
            'status' => Service::STATUS_SERVICE_OPEN,
        ]);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('services.machines.count.store', [$service->id, $machine->id]), [
                'counts' => [
                    $bin->id => [
                        'quantity' => 5,
                        'spoilage' => 1,
                    ],
                ],
            ])
            ->assertRedirect(route('services.show', $service->id));

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('services.machines.count.store', [$service->id, $machine->id]), [
                'counts' => [
                    $bin->id => [
                        'quantity' => 4,
                        'spoilage' => 2,
                    ],
                ],
            ])
            ->assertRedirect(route('services.show', $service->id));

        $this->assertSame(1, Transaction::query()->where('service_id', $service->id)->where('transaction_type', Transaction::TYPE_COUNT)->count());
        $this->assertDatabaseHas('tbl_transactions', [
            'service_id' => $service->id,
            'bin_id' => $bin->id,
            'transaction_type' => Transaction::TYPE_COUNT,
            'quantity' => 4,
            'spoilage' => 2,
        ]);
    }

    public function test_count_rejects_negative_spoilage(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $account = $this->createAccount('Spoilage Validation Account');
        $this->attachUserToAccount($user, $account, 'owner');
        $this->seedServiceTypes();

        $route = $this->createRoute($account, 'Validation Route');
        $location = $this->createLocation($account, $route, 'Validation Stop');
        $warehouse = $this->createWarehouse($account, 'Validation Warehouse');
        $machine = $this->createMachine($account, $location, 'Snack');
        $product = $this->createProduct($account, 'Candy');
        $bin = $this->createBin($account, $machine, $product, 'A1', 10, 1.00);

        $service = Service::create([
            'account_id' => $account->id,
            'location_id' => $location->id,
            'warehouse_id' => $warehouse->id,
            'user_id' => $user->id,
            'service_type' => Service::TYPE_LOCATION_SERVICE,
            'service_date' => '2026-07-19',
            'opened_at' => '2026-07-19 08:00:00',
            'status' => Service::STATUS_SERVICE_OPEN,
        ]);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->from(route('services.machines.count', [$service->id, $machine->id]))
            ->post(route('services.machines.count.store', [$service->id, $machine->id]), [
                'counts' => [
                    $bin->id => [
                        'quantity' => 4,
                        'spoilage' => -1,
                    ],
                ],
            ])
            ->assertRedirect(route('services.machines.count', [$service->id, $machine->id]))
            ->assertSessionHasErrors('counts.'.$bin->id.'.spoilage');
    }

    public function test_count_plus_spoilage_greater_than_opening_inventory_blocks_service_completion(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $account = $this->createAccount('Spoilage Blocking Account');
        $this->attachUserToAccount($user, $account, 'owner');
        $this->seedServiceTypes();

        $route = $this->createRoute($account, 'Blocking Route');
        $location = $this->createLocation($account, $route, 'Blocking Stop');
        $warehouse = $this->createWarehouse($account, 'Blocking Warehouse');
        $machine = $this->createMachine($account, $location, 'Snack');
        $product = $this->createProduct($account, 'Juice');
        $bin = $this->createBin($account, $machine, $product, 'A1', 10, 2.00);

        $previousService = Service::create([
            'account_id' => $account->id,
            'location_id' => $location->id,
            'warehouse_id' => $warehouse->id,
            'user_id' => $user->id,
            'service_type' => Service::TYPE_LOCATION_SERVICE,
            'service_date' => '2026-07-18',
            'opened_at' => '2026-07-18 08:00:00',
            'completed_at' => '2026-07-18 10:00:00',
            'closed_at' => '2026-07-18 10:30:00',
            'amount_collected' => 0,
            'status' => Service::STATUS_SERVICE_CLOSED,
        ]);

        $this->createTransaction($account, $previousService, $bin, Transaction::TYPE_CURRENT_INVENTORY, 5, '2026-07-18 09:00:00');

        $service = Service::create([
            'account_id' => $account->id,
            'location_id' => $location->id,
            'warehouse_id' => $warehouse->id,
            'user_id' => $user->id,
            'service_type' => Service::TYPE_LOCATION_SERVICE,
            'service_date' => '2026-07-19',
            'opened_at' => '2026-07-19 08:00:00',
            'status' => Service::STATUS_SERVICE_OPEN,
        ]);

        $this->createTransaction($account, $service, $bin, Transaction::TYPE_COUNT, 4, '2026-07-19 09:00:00', 2);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->from(route('services.show', $service->id))
            ->post(route('services.complete', $service->id))
            ->assertRedirect(route('services.show', $service->id))
            ->assertSessionHasErrors('service');

        $service->refresh();
        $this->assertSame(Service::STATUS_SERVICE_OPEN, $service->status);
        $this->assertDatabaseCount('tbl_service_sales', 0);
    }

    public function test_fill_requires_a_count_before_inventory_can_be_added_to_a_bin(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $account = $this->createAccount('Count First Account');
        $this->attachUserToAccount($user, $account, 'owner');
        $this->seedServiceTypes();

        $route = $this->createRoute($account, 'Count Route');
        $location = $this->createLocation($account, $route, 'Count Stop');
        $warehouse = $this->createWarehouse($account, 'Count Warehouse');
        $machine = $this->createMachine($account, $location, 'Snack');
        $product = $this->createProduct($account, 'Granola Bar');
        $bin = $this->createBin($account, $machine, $product, 'A1', 10, 1.75);

        $service = Service::create([
            'account_id' => $account->id,
            'location_id' => $location->id,
            'warehouse_id' => $warehouse->id,
            'user_id' => $user->id,
            'service_type' => Service::TYPE_LOCATION_SERVICE,
            'service_date' => '2026-07-19',
            'opened_at' => '2026-07-19 08:00:00',
            'status' => Service::STATUS_SERVICE_OPEN,
        ]);

        // Enforce the count-first workflow so fill inventory never changes the sales interval retroactively.
        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->from(route('services.show', $service->id))
            ->post(route('services.machines.fill.store', [$service->id, $machine->id]), [
                'quantities' => [
                    $bin->id => 2,
                ],
            ])
            ->assertRedirect(route('services.show', $service->id))
            ->assertSessionHasErrors('quantity');

        $this->assertDatabaseMissing('tbl_transactions', [
            'service_id' => $service->id,
            'bin_id' => $bin->id,
            'transaction_type' => Transaction::TYPE_FILL,
        ]);
    }

    public function test_service_detail_groups_partial_machine_sales_without_counting_baselines_in_totals(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $account = $this->createAccount('Grouped Sales Account');
        $this->attachUserToAccount($user, $account, 'owner');
        $this->seedServiceTypes();

        $route = $this->createRoute($account, 'Grouped Route');
        $location = $this->createLocation($account, $route, 'Grouped Stop');
        $warehouse = $this->createWarehouse($account, 'Grouped Warehouse');
        $machine = $this->createMachine($account, $location, 'Snack');
        $productOne = $this->createProduct($account, 'Chips');
        $productTwo = $this->createProduct($account, 'Cookies');
        $binOne = $this->createBin($account, $machine, $productOne, 'A1', 10, 2.00);
        $binTwo = $this->createBin($account, $machine, $productTwo, 'A2', 10, 1.25);

        $previousService = Service::create([
            'account_id' => $account->id,
            'location_id' => $location->id,
            'warehouse_id' => $warehouse->id,
            'user_id' => $user->id,
            'service_type' => Service::TYPE_LOCATION_SERVICE,
            'service_date' => '2026-07-18',
            'opened_at' => '2026-07-18 08:00:00',
            'completed_at' => '2026-07-18 10:00:00',
            'closed_at' => '2026-07-18 10:30:00',
            'amount_collected' => 0,
            'status' => Service::STATUS_SERVICE_CLOSED,
        ]);

        $this->createTransaction($account, $previousService, $binOne, Transaction::TYPE_CURRENT_INVENTORY, 20, '2026-07-18 09:00:00');

        $service = Service::create([
            'account_id' => $account->id,
            'location_id' => $location->id,
            'warehouse_id' => $warehouse->id,
            'user_id' => $user->id,
            'service_type' => Service::TYPE_LOCATION_SERVICE,
            'service_date' => '2026-07-19',
            'opened_at' => '2026-07-19 08:00:00',
            'closed_at' => null,
            'status' => Service::STATUS_SERVICE_OPEN,
        ]);

        $this->createTransaction($account, $service, $binOne, Transaction::TYPE_COUNT, 8, '2026-07-19 09:00:00', 2);
        $this->createTransaction($account, $service, $binTwo, Transaction::TYPE_COUNT, 4, '2026-07-19 09:05:00', 1);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('services.complete', $service->id))
            ->assertRedirect(route('services.show', $service->id));

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('services.show', $service->id))
            ->assertOk()
            ->assertSeeText('Sales Breakdown')
            ->assertSeeText('Partial')
            ->assertSeeText('$20.00')
            ->assertSeeText('Spoilage')
            ->assertDontSeeText('Additions')
            ->assertDontSeeText('Removals')
            ->assertViewHas('machineSalesGroups', function ($groups) use ($machine, $binOne, $binTwo) {
                $machineGroup = $groups->firstWhere('machine.id', $machine->id);

                return $machineGroup !== null
                    && $machineGroup['sales']->pluck('bin_id')->all() === [$binOne->id, $binTwo->id]
                    && $machineGroup['calculated_count'] === 1
                    && $machineGroup['baseline_count'] === 1
                    && $machineGroup['total_units_sold'] === 10
                    && $machineGroup['total_sales'] === '20.00';
            });
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

    public function test_service_detail_uses_agent_date_and_time_formats_for_visible_values(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $account = $this->createAccount('Display Account');
        $this->attachUserToAccount($user, $account, 'owner');
        $this->seedServiceTypes();

        $route = $this->createRoute($account, 'Display Route');
        $location = $this->createLocation($account, $route, 'Display Stop');
        $warehouse = $this->createWarehouse($account, 'Display Warehouse');
        $machine = $this->createMachine($account, $location, 'Snack');
        $product = $this->createProduct($account, 'Cola');
        $bin = $this->createBin($account, $machine, $product, 'A1', 10, 2.00);

        $service = Service::create([
            'account_id' => $account->id,
            'location_id' => $location->id,
            'warehouse_id' => $warehouse->id,
            'user_id' => $user->id,
            'service_type' => Service::TYPE_LOCATION_SERVICE,
            'service_date' => '2026-07-18',
            'opened_at' => '2026-07-18 08:15:30',
            'completed_at' => '2026-07-18 09:45:15',
            'closed_at' => '2026-07-18 10:30:45',
            'amount_collected' => 0,
            'status' => Service::STATUS_SERVICE_CLOSED,
        ]);

        $this->createTransaction($account, $service, $bin, Transaction::TYPE_COUNT, 6, '2026-07-17 11:22:33', 2);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('services.show', $service->id))
            ->assertOk()
            ->assertSeeText('07-18-2026')
            ->assertSeeText('08:15:30')
            ->assertSeeText('09:45:15')
            ->assertSeeText('10:30:45')
            ->assertSeeText('07-17-2026')
            ->assertSeeText('11:22:33')
            ->assertSeeText('Spoilage: 2')
            ->assertDontSeeText('Jul')
            ->assertDontSeeText('AM')
            ->assertSee('datetime="2026-07-18"', false)
            ->assertSee('datetime="2026-07-18T08:15:30+00:00"', false)
            ->assertSee('datetime="2026-07-17"', false)
            ->assertSee('datetime="2026-07-17T11:22:33+00:00"', false);
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

    protected function createTransaction(
        Account $account,
        Service $service,
        Bin $bin,
        string $type,
        int $quantity,
        string $transactionAt,
        int $spoilage = 0,
    ): Transaction {
        // Keep test transaction setup explicit so count spoilage can be asserted alongside quantity.
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
