<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Bin;
use App\Models\InventoryLedger;
use App\Models\Machine;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\RouteLocation;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\User;
use App\Models\VendingRoute;
use App\Models\Vendor;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_financial_models_write_audit_entries_for_create_update_and_delete(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $fixture = $this->createFixtureAccountGraph();

        $this->actingAs($user);

        $service = Service::create([
            'account_id' => $fixture['account']->id,
            'location_id' => $fixture['location']->id,
            'warehouse_id' => $fixture['warehouse']->id,
            'user_id' => $user->id,
            'service_type' => Service::TYPE_LOCATION_SERVICE,
            'service_date' => '2026-07-22',
            'status' => Service::STATUS_AWAITING_SERVICE,
        ]);
        $service->update(['status' => Service::STATUS_SERVICE_OPEN]);
        $service->delete();

        $transactionService = Service::create([
            'account_id' => $fixture['account']->id,
            'location_id' => $fixture['location']->id,
            'warehouse_id' => $fixture['warehouse']->id,
            'user_id' => $user->id,
            'service_type' => Service::TYPE_LOCATION_SERVICE,
            'service_date' => '2026-07-22',
            'status' => Service::STATUS_SERVICE_OPEN,
        ]);

        $transaction = Transaction::create([
            'account_id' => $fixture['account']->id,
            'service_id' => $transactionService->id,
            'machine_id' => $fixture['machine']->id,
            'bin_id' => $fixture['bin']->id,
            'product_id' => $fixture['product']->id,
            'transaction_type' => Transaction::TYPE_COUNT,
            'quantity' => 4,
            'spoilage' => 1,
            'transaction_at' => '2026-07-22 09:00:00',
            'price' => 1.75,
            'unit_cost' => 0.85,
        ]);
        $transaction->update(['quantity' => 5]);
        $transaction->delete();

        $purchase = Purchase::create([
            'account_id' => $fixture['account']->id,
            'vendor_id' => $fixture['vendor']->id,
            'warehouse_id' => $fixture['warehouse']->id,
            'invoice_number' => 'INV-100',
            'purchase_date' => '2026-07-22',
            'status' => Purchase::STATUS_POSTED,
            'notes' => 'Initial post',
        ]);
        $purchase->update(['status' => Purchase::STATUS_VOIDED]);
        $purchase->delete();

        $purchaseForItem = Purchase::create([
            'account_id' => $fixture['account']->id,
            'vendor_id' => $fixture['vendor']->id,
            'warehouse_id' => $fixture['warehouse']->id,
            'invoice_number' => 'INV-101',
            'purchase_date' => '2026-07-22',
            'status' => Purchase::STATUS_POSTED,
            'notes' => 'Purchase item audit parent',
        ]);

        $purchaseItem = PurchaseItem::create([
            'account_id' => $fixture['account']->id,
            'purchase_id' => $purchaseForItem->id,
            'product_id' => $fixture['product']->id,
            'quantity' => 12,
            'line_total' => 30.00,
            'unit_cost' => 2.5000,
        ]);
        $purchaseItem->update(['quantity' => 10]);
        $purchaseItem->delete();

        $inventoryLedger = InventoryLedger::create([
            'account_id' => $fixture['account']->id,
            'warehouse_id' => $fixture['warehouse']->id,
            'product_id' => $fixture['product']->id,
            'movement_type' => InventoryLedger::MOVEMENT_TYPE_ADJUSTMENT,
            'quantity_delta' => 6,
            'unit_cost' => 1.2500,
            'total_cost' => 7.5000,
            'source_type' => 'manual_adjustment',
            'source_id' => 99,
            'movement_at' => '2026-07-22 10:00:00',
            'notes' => 'Cycle count adjustment',
        ]);
        $inventoryLedger->delete();

        $this->assertAuditEvents($fixture['account']->id, $user->id, Service::class, $service->id, ['created', 'updated', 'deleted']);
        $this->assertAuditEvents($fixture['account']->id, $user->id, Transaction::class, $transaction->id, ['created', 'updated', 'deleted']);
        $this->assertAuditEvents($fixture['account']->id, $user->id, Purchase::class, $purchase->id, ['created', 'updated', 'deleted']);
        $this->assertAuditEvents($fixture['account']->id, $user->id, PurchaseItem::class, $purchaseItem->id, ['created', 'updated', 'deleted']);
        $this->assertAuditEvents($fixture['account']->id, $user->id, InventoryLedger::class, $inventoryLedger->id, ['created', 'deleted']);
    }

    public function test_update_that_changes_nothing_does_not_write_an_updated_audit_row(): void
    {
        $fixture = $this->createFixtureAccountGraph();

        $service = Service::create([
            'account_id' => $fixture['account']->id,
            'location_id' => $fixture['location']->id,
            'warehouse_id' => $fixture['warehouse']->id,
            'user_id' => null,
            'service_type' => Service::TYPE_LOCATION_SERVICE,
            'service_date' => '2026-07-22',
            'status' => Service::STATUS_AWAITING_SERVICE,
        ]);

        $service->save();

        $this->assertSame(
            1,
            AuditLog::query()
                ->where('auditable_type', Service::class)
                ->where('auditable_id', $service->id)
                ->count()
        );
        $this->assertDatabaseMissing('tbl_audit_log', [
            'auditable_type' => Service::class,
            'auditable_id' => $service->id,
            'event' => AuditLog::EVENT_UPDATED,
        ]);
    }

    public function test_changes_without_authenticated_user_are_logged_as_system_actions(): void
    {
        $fixture = $this->createFixtureAccountGraph();

        $service = Service::create([
            'account_id' => $fixture['account']->id,
            'location_id' => $fixture['location']->id,
            'warehouse_id' => $fixture['warehouse']->id,
            'user_id' => null,
            'service_type' => Service::TYPE_LOCATION_SERVICE,
            'service_date' => '2026-07-22',
            'status' => Service::STATUS_AWAITING_SERVICE,
        ]);

        $auditEntry = AuditLog::query()
            ->where('auditable_type', Service::class)
            ->where('auditable_id', $service->id)
            ->where('event', AuditLog::EVENT_CREATED)
            ->firstOrFail();

        $this->assertNull($auditEntry->user_id);
    }

    public function test_updated_audit_payload_contains_only_changed_fields_with_old_and_new_values(): void
    {
        $fixture = $this->createFixtureAccountGraph();

        $purchase = Purchase::create([
            'account_id' => $fixture['account']->id,
            'vendor_id' => $fixture['vendor']->id,
            'warehouse_id' => $fixture['warehouse']->id,
            'invoice_number' => 'INV-200',
            'purchase_date' => '2026-07-22',
            'status' => Purchase::STATUS_POSTED,
            'notes' => 'Original note',
        ]);

        $purchase->update([
            'status' => Purchase::STATUS_VOIDED,
            'notes' => 'Updated note',
        ]);

        $auditEntry = AuditLog::query()
            ->where('auditable_type', Purchase::class)
            ->where('auditable_id', $purchase->id)
            ->where('event', AuditLog::EVENT_UPDATED)
            ->latest('id')
            ->firstOrFail();

        $this->assertEqualsCanonicalizing(['notes', 'status'], array_keys($auditEntry->changes));
        $this->assertSame([
            'old' => 'Original note',
            'new' => 'Updated note',
        ], $auditEntry->changes['notes']);
        $this->assertSame([
            'old' => Purchase::STATUS_POSTED,
            'new' => Purchase::STATUS_VOIDED,
        ], $auditEntry->changes['status']);
        $this->assertArrayNotHasKey('invoice_number', $auditEntry->changes);
        $this->assertArrayNotHasKey('purchase_date', $auditEntry->changes);
    }

    public function test_inventory_ledger_updates_do_not_write_updated_audit_rows(): void
    {
        $fixture = $this->createFixtureAccountGraph();

        $ledger = InventoryLedger::create([
            'account_id' => $fixture['account']->id,
            'warehouse_id' => $fixture['warehouse']->id,
            'product_id' => $fixture['product']->id,
            'movement_type' => InventoryLedger::MOVEMENT_TYPE_ADJUSTMENT,
            'quantity_delta' => 3,
            'unit_cost' => 1.1000,
            'total_cost' => 3.3000,
            'source_type' => 'manual_adjustment',
            'source_id' => 42,
            'movement_at' => '2026-07-22 12:00:00',
            'notes' => 'Initial ledger row',
        ]);

        $ledger->update(['notes' => 'Modified ledger row']);

        $this->assertDatabaseMissing('tbl_audit_log', [
            'auditable_type' => InventoryLedger::class,
            'auditable_id' => $ledger->id,
            'event' => AuditLog::EVENT_UPDATED,
        ]);
    }

    protected function assertAuditEvents(int $accountId, ?int $userId, string $type, int $id, array $events): void
    {
        $loggedEvents = AuditLog::query()
            ->where('auditable_type', $type)
            ->where('auditable_id', $id)
            ->orderBy('id')
            ->get();

        $this->assertSame($events, $loggedEvents->pluck('event')->all());

        foreach ($loggedEvents as $entry) {
            $this->assertSame($accountId, $entry->account_id);
            $this->assertSame($userId, $entry->user_id);
            $this->assertSame($type, $entry->auditable_type);
            $this->assertSame($id, $entry->auditable_id);
            $this->assertNotNull($entry->created_at);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function createFixtureAccountGraph(): array
    {
        $account = Account::withoutEvents(fn () => Account::create([
            'account_name' => 'Audit Account',
            'slug' => 'audit-account-'.uniqid(),
            'status' => Account::STATUS_ACTIVE,
            'billing_email' => 'audit@example.com',
        ]));

        $warehouse = Warehouse::create([
            'account_id' => $account->id,
            'warehouse_name' => 'Main Warehouse',
        ]);

        $vendor = Vendor::create([
            'account_id' => $account->id,
            'vendor_name' => 'Primary Vendor',
        ]);

        $product = Product::create([
            'account_id' => $account->id,
            'vendor_id' => $vendor->id,
            'sku' => 'AUD-001',
            'product_name' => 'Audit Product',
            'category' => 'Snacks',
            'brand' => 'Audit Brand',
        ]);

        $route = VendingRoute::create([
            'account_id' => $account->id,
            'route_name' => 'Audit Route',
            'description' => 'Audit route',
        ]);

        $location = \App\Models\Location::create([
            'account_id' => $account->id,
            'location_name' => 'Audit Location',
        ]);

        RouteLocation::create([
            'account_id' => $account->id,
            'route_id' => $route->id,
            'location_id' => $location->id,
            'stop_order' => 1,
            'is_primary' => true,
        ]);

        $machine = Machine::create([
            'account_id' => $account->id,
            'location_id' => $location->id,
            'type' => 'Snack',
            'status' => Machine::STATUS_ACTIVE,
        ]);

        $bin = Bin::create([
            'account_id' => $account->id,
            'machine_id' => $machine->id,
            'product_id' => $product->id,
            'bin_code' => 'A1',
            'capacity' => 10,
            'price' => 1.75,
        ]);

        return compact('account', 'warehouse', 'vendor', 'product', 'route', 'location', 'machine', 'bin');
    }
}
