<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\InventoryLedger;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryCostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryOnHandReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_on_hand_report_matches_inventory_cost_service_and_groups_by_warehouse_and_category(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Inventory On Hand Account');
        $otherAccount = $this->createAccount('Foreign Inventory On Hand Account');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_MANAGER);

        $mainWarehouse = $this->createWarehouse($account, 'Main Warehouse');
        $overflowWarehouse = $this->createWarehouse($account, 'Overflow Warehouse');
        $emptyWarehouse = $this->createWarehouse($account, 'Quiet Warehouse');
        $foreignWarehouse = $this->createWarehouse($otherAccount, 'Foreign Warehouse');

        $cola = $this->createProduct($account, 'beverage', 'Cola', 'cola');
        $water = $this->createProduct($account, 'beverage', 'Water', 'water');
        $chips = $this->createProduct($account, 'snack', 'Chips', 'chips');
        $nuts = $this->createProduct($account, 'snack', 'Nuts', 'nuts');
        $foreignCola = $this->createProduct($otherAccount, 'beverage', 'Foreign Cola', 'foreign-cola');

        $this->createLedgerEntry($account, $mainWarehouse, $cola, 10, 15.0000);
        $this->createLedgerEntry($account, $mainWarehouse, $cola, -3, -4.5000);
        $this->createLedgerEntry($account, $mainWarehouse, $water, 0, 0.0000);
        $this->createLedgerEntry($account, $mainWarehouse, $chips, -2, -3.0000);
        $this->createLedgerEntry($account, $overflowWarehouse, $nuts, 4, 8.0000);
        $this->createLedgerEntry($otherAccount, $foreignWarehouse, $foreignCola, 50, 75.0000);

        $inventoryCostService = app(InventoryCostService::class);
        $colaSummary = $inventoryCostService->getWarehouseInventorySummary($account->id, $mainWarehouse->id, $cola->id);
        $waterSummary = $inventoryCostService->getWarehouseInventorySummary($account->id, $mainWarehouse->id, $water->id);
        $chipsSummary = $inventoryCostService->getWarehouseInventorySummary($account->id, $mainWarehouse->id, $chips->id);
        $nutsSummary = $inventoryCostService->getWarehouseInventorySummary($account->id, $overflowWarehouse->id, $nuts->id);

        $this->assertSame(7, $colaSummary['quantity_on_hand']);
        $this->assertSame(0, $waterSummary['quantity_on_hand']);
        $this->assertSame(-2, $chipsSummary['quantity_on_hand']);
        $this->assertSame(4, $nutsSummary['quantity_on_hand']);

        $response = $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('reports.inventory-on-hand'));

        $response
            ->assertOk()
            ->assertSeeText('Inventory On Hand')
            ->assertSeeTextInOrder(['Main Warehouse', 'Overflow Warehouse', 'Quiet Warehouse'])
            ->assertSeeTextInOrder(['Beverage', 'Cola', 'Water', 'Snack', 'Chips'])
            ->assertSeeText('7 units')
            ->assertSeeText('5 units')
            ->assertSeeText('4 units')
            ->assertSeeText('9 units')
            ->assertSeeText('No inventory on hand for this warehouse.')
            ->assertSeeText('Zero on hand.')
            ->assertSeeText('Negative balance flagged for review.')
            ->assertDontSeeText('Foreign Cola');

        $response
            ->assertDontSee('type="date"', false)
            ->assertDontSee('name="date_from"', false)
            ->assertDontSee('name="date_to"', false);
    }

    public function test_empty_inventory_on_hand_report_shows_a_clear_message_without_filters(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Empty Inventory On Hand Account');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);

        $response = $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('reports.inventory-on-hand'));

        $response
            ->assertOk()
            ->assertSeeText('No inventory ledger balances are available yet.')
            ->assertSeeText('Grand Total')
            ->assertSeeText('0 units')
            ->assertDontSee('type="date"', false);
    }

    public function test_inventory_on_hand_report_is_forbidden_for_technicians_and_viewers(): void
    {
        $account = $this->createAccount('Inventory On Hand Permissions');

        foreach ([AccountUser::ROLE_TECHNICIAN, AccountUser::ROLE_VIEWER] as $role) {
            $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
            $this->attachUserToAccount($user, $account, $role);

            $this->actingAs($user)
                ->withSession(['current_account_id' => $account->id])
                ->get(route('reports.inventory-on-hand'))
                ->assertForbidden();
        }
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

    protected function createWarehouse(Account $account, string $name): Warehouse
    {
        return Warehouse::create([
            'account_id' => $account->id,
            'warehouse_name' => $name,
            'address' => null,
            'city' => null,
            'state' => null,
            'zip_code' => null,
        ]);
    }

    protected function createProduct(Account $account, string $category, string $productName, string $skuPrefix): Product
    {
        return Product::create([
            'account_id' => $account->id,
            'vendor_id' => null,
            'category' => $category,
            'brand' => $productName.' Brand',
            'sku' => $skuPrefix.'-sku-'.uniqid(),
            'product_name' => $productName,
            'size' => null,
            'package_type' => null,
            'barcode' => null,
            'reorder_point' => null,
        ]);
    }

    protected function createLedgerEntry(Account $account, Warehouse $warehouse, Product $product, int $quantityDelta, float $totalCost): InventoryLedger
    {
        return InventoryLedger::create([
            'account_id' => $account->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'movement_type' => InventoryLedger::MOVEMENT_TYPE_ADJUSTMENT,
            'quantity_delta' => $quantityDelta,
            'unit_cost' => $quantityDelta !== 0 ? round($totalCost / $quantityDelta, 4) : 0,
            'total_cost' => $totalCost,
            'source_type' => 'test',
            'source_id' => null,
            'movement_at' => now(),
            'notes' => null,
        ]);
    }
}
