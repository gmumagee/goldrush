<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchasesByVendorReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchases_by_vendor_report_groups_purchase_totals_and_excludes_voided_purchases(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Purchases by Vendor Account');
        $otherAccount = $this->createAccount('Foreign Purchases by Vendor Account');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_MANAGER);

        $alphaVendor = $this->createVendor($account, 'Alpha Supply');
        $betaVendor = $this->createVendor($account, 'Beta Wholesale');
        $quietVendor = $this->createVendor($account, 'Quiet Vendor');
        $foreignVendor = $this->createVendor($otherAccount, 'Foreign Supply');

        $mainWarehouse = $this->createWarehouse($account, 'Main Warehouse');
        $overflowWarehouse = $this->createWarehouse($account, 'Overflow Warehouse');
        $foreignWarehouse = $this->createWarehouse($otherAccount, 'Foreign Warehouse');
        $cola = $this->createProduct($account, 'Cola', 'cola');
        $chips = $this->createProduct($account, 'Chips', 'chips');
        $foreignProduct = $this->createProduct($otherAccount, 'Foreign Cola', 'foreign-cola');

        $alphaFirst = $this->createPurchase($account, $alphaVendor, $mainWarehouse, [
            'purchase_date' => '2026-07-10',
            'status' => Purchase::STATUS_POSTED,
        ]);
        $this->createPurchaseItem($account, $alphaFirst, $cola, ['line_total' => 10.00]);
        $this->createPurchaseItem($account, $alphaFirst, $chips, ['line_total' => 15.00]);

        $alphaSecond = $this->createPurchase($account, $alphaVendor, $overflowWarehouse, [
            'purchase_date' => '2026-07-12',
            'status' => Purchase::STATUS_POSTED,
        ]);
        $this->createPurchaseItem($account, $alphaSecond, $cola, ['line_total' => 20.00]);

        $alphaVoided = $this->createPurchase($account, $alphaVendor, $mainWarehouse, [
            'purchase_date' => '2026-07-11',
            'status' => Purchase::STATUS_VOIDED,
        ]);
        $this->createPurchaseItem($account, $alphaVoided, $cola, ['line_total' => 99.00]);

        $betaPurchase = $this->createPurchase($account, $betaVendor, $mainWarehouse, [
            'purchase_date' => '2026-07-11',
            'status' => Purchase::STATUS_POSTED,
        ]);
        $this->createPurchaseItem($account, $betaPurchase, $chips, ['line_total' => 30.00]);

        $outOfRangePurchase = $this->createPurchase($account, $betaVendor, $mainWarehouse, [
            'purchase_date' => '2026-06-30',
            'status' => Purchase::STATUS_POSTED,
        ]);
        $this->createPurchaseItem($account, $outOfRangePurchase, $chips, ['line_total' => 55.00]);

        $foreignPurchase = $this->createPurchase($otherAccount, $foreignVendor, $foreignWarehouse, [
            'purchase_date' => '2026-07-11',
            'status' => Purchase::STATUS_POSTED,
        ]);
        $this->createPurchaseItem($otherAccount, $foreignPurchase, $foreignProduct, ['line_total' => 200.00]);

        $response = $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('reports.purchases-by-vendor', [
                'date_from' => '2026-07-10',
                'date_to' => '2026-07-12',
            ]));

        $response
            ->assertOk()
            ->assertSeeText('Purchases by Vendor')
            ->assertSeeTextInOrder(['Alpha Supply', 'Beta Wholesale'])
            ->assertSeeText('$45.00')
            ->assertSeeText('$30.00')
            ->assertSeeText('2 purchases')
            ->assertSeeText('1 purchase')
            ->assertSeeText('Grand Total')
            ->assertSeeText('$75.00')
            ->assertSeeTextInOrder(['07-10-2026', '07-12-2026'])
            ->assertSeeText('Main Warehouse')
            ->assertSeeText('Overflow Warehouse')
            ->assertSeeText('Posted')
            ->assertDontSeeText('99.00')
            ->assertDontSeeText('Foreign Supply');

        $response
            ->assertSee('type="date"', false)
            ->assertSee('value="2026-07-10"', false)
            ->assertSee('value="2026-07-12"', false);
    }

    public function test_vendor_filter_narrows_the_report_to_a_single_vendor_group(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Purchases by Vendor Filter Account');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);

        $alphaVendor = $this->createVendor($account, 'Alpha Supply');
        $betaVendor = $this->createVendor($account, 'Beta Wholesale');
        $warehouse = $this->createWarehouse($account, 'Main Warehouse');
        $cola = $this->createProduct($account, 'Cola', 'cola');
        $chips = $this->createProduct($account, 'Chips', 'chips');

        $alphaPurchase = $this->createPurchase($account, $alphaVendor, $warehouse, [
            'purchase_date' => '2026-07-10',
            'status' => Purchase::STATUS_POSTED,
        ]);
        $this->createPurchaseItem($account, $alphaPurchase, $cola, ['line_total' => 15.00]);

        $betaPurchase = $this->createPurchase($account, $betaVendor, $warehouse, [
            'purchase_date' => '2026-07-11',
            'status' => Purchase::STATUS_POSTED,
        ]);
        $this->createPurchaseItem($account, $betaPurchase, $chips, ['line_total' => 22.00]);

        $response = $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('reports.purchases-by-vendor', [
                'vendor_id' => $betaVendor->id,
                'date_from' => '2026-07-10',
                'date_to' => '2026-07-12',
            ]));

        $response
            ->assertOk()
            ->assertSeeText('Beta Wholesale')
            ->assertSeeText('$22.00')
            ->assertDontSeeText('$15.00');
    }

    public function test_empty_purchases_by_vendor_range_shows_a_clear_message(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Empty Purchases by Vendor Account');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);

        $response = $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('reports.purchases-by-vendor', [
                'date_from' => '2026-07-01',
                'date_to' => '2026-07-31',
            ]));

        $response
            ->assertOk()
            ->assertSeeText('No purchases in this date range.')
            ->assertSeeText('Grand Total')
            ->assertSeeText('$0.00');
    }

    public function test_purchases_by_vendor_report_is_forbidden_for_technicians_and_viewers(): void
    {
        $account = $this->createAccount('Purchases by Vendor Permissions');

        foreach ([AccountUser::ROLE_TECHNICIAN, AccountUser::ROLE_VIEWER] as $role) {
            $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
            $this->attachUserToAccount($user, $account, $role);

            $this->actingAs($user)
                ->withSession(['current_account_id' => $account->id])
                ->get(route('reports.purchases-by-vendor'))
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

    protected function createVendor(Account $account, string $name): Vendor
    {
        return Vendor::create([
            'account_id' => $account->id,
            'vendor_name' => $name,
            'location' => null,
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

    protected function createProduct(Account $account, string $name, string $skuPrefix): Product
    {
        return Product::create([
            'account_id' => $account->id,
            'vendor_id' => null,
            'category' => 'beverage',
            'brand' => $name.' Brand',
            'sku' => $skuPrefix.'-sku-'.uniqid(),
            'product_name' => $name,
            'size' => null,
            'package_type' => null,
            'barcode' => null,
            'reorder_point' => null,
        ]);
    }

    protected function createPurchase(Account $account, Vendor $vendor, Warehouse $warehouse, array $attributes = []): Purchase
    {
        return Purchase::create([
            'account_id' => $account->id,
            'vendor_id' => $vendor->id,
            'warehouse_id' => $warehouse->id,
            'invoice_number' => $attributes['invoice_number'] ?? null,
            'purchase_date' => $attributes['purchase_date'] ?? '2026-07-10',
            'status' => $attributes['status'] ?? Purchase::STATUS_POSTED,
            'notes' => null,
        ]);
    }

    protected function createPurchaseItem(Account $account, Purchase $purchase, Product $product, array $attributes = []): PurchaseItem
    {
        return PurchaseItem::create([
            'account_id' => $account->id,
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'quantity' => $attributes['quantity'] ?? 1,
            'line_total' => $attributes['line_total'] ?? 10.00,
            'unit_cost' => $attributes['unit_cost'] ?? '1.0000',
        ]);
    }
}
