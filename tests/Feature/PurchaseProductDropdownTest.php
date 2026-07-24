<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseProductDropdownTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_create_product_dropdown_uses_display_name_and_variant_ordering(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Purchase Product Dropdown Account');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);

        $warehouse = $this->createWarehouse($account, 'Main Warehouse');
        $familyName = 'Purchase Variant Cola';

        $colaBottle = $this->createProduct($account, $familyName, '20 oz', 'Bottle');
        $colaCan = $this->createProduct($account, $familyName, '12 oz', 'Can');
        $colaLoose = $this->createProduct($account, $familyName, null, null);
        $spriteBottle = $this->createProduct($account, 'Purchase Variant Lemon-Lime', '20 oz', 'Bottle');

        $response = $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('purchases.create'));

        $response
            ->assertOk()
            ->assertSee('Purchase Variant Cola \\u00b7 12 oz \\u00b7 Can', false)
            ->assertSee('Purchase Variant Cola \\u00b7 20 oz \\u00b7 Bottle', false)
            ->assertSee('"label":"Purchase Variant Cola"', false)
            ->assertSee('Purchase Variant Lemon-Lime \\u00b7 20 oz \\u00b7 Bottle', false);

        $products = $response->viewData('products');
        $this->assertProductBlockOrder($products->pluck('id')->all(), [
            $colaCan->id,
            $colaBottle->id,
            $colaLoose->id,
        ]);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('purchases.store'), [
                'warehouse_id' => $warehouse->id,
                'purchase_date' => '07-24-2026',
                'items' => [
                    [
                        'product_id' => $spriteBottle->id,
                        'quantity' => 3,
                        'line_total' => 7.50,
                    ],
                ],
            ])
            ->assertRedirect();
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
            'address' => '100 Warehouse Way',
            'city' => 'New York',
            'state' => 'NY',
            'zip_code' => '10001',
        ]);
    }

    protected function createProduct(Account $account, string $name, ?string $size, ?string $packageType): Product
    {
        return Product::create([
            'account_id' => $account->id,
            'vendor_id' => null,
            'category' => 'Beverage',
            'brand' => 'Brand',
            'sku' => uniqid('sku-', true),
            'product_name' => $name,
            'size' => $size,
            'package_type' => $packageType,
            'barcode' => null,
        ]);
    }

    protected function assertProductBlockOrder(array $productIds, array $expectedIds): void
    {
        $firstIndex = array_search($expectedIds[0], $productIds, true);

        $this->assertNotFalse($firstIndex);
        $this->assertSame($expectedIds, array_slice($productIds, $firstIndex, count($expectedIds)));
    }
}
