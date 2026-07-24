<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\Bin;
use App\Models\Location;
use App\Models\Machine;
use App\Models\Product;
use App\Models\RouteLocation;
use App\Models\User;
use App\Models\VendingRoute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BinProductDropdownTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_display_name_accessor_formats_variants_and_omits_missing_segments(): void
    {
        $fullVariant = new Product([
            'product_name' => 'Coca-Cola',
            'size' => '12 oz',
            'package_type' => 'Can',
        ]);
        $missingPackageType = new Product([
            'product_name' => 'Sprite',
            'size' => '20 oz',
            'package_type' => null,
        ]);
        $missingVariantDetails = new Product([
            'product_name' => 'Water',
            'size' => null,
            'package_type' => null,
        ]);

        $this->assertSame('Coca-Cola · 12 oz · Can', $fullVariant->display_name);
        $this->assertSame('Sprite · 20 oz', $missingPackageType->display_name);
        $this->assertSame('Water', $missingVariantDetails->display_name);
    }

    public function test_all_bin_product_dropdowns_use_display_names_keep_selected_products_and_group_variants_together(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Bin Product Dropdown Account');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);

        $route = $this->createRoute($account, 'Bin Product Route');
        $location = $this->createCustomerLocation($account, $route, 'Bin Product Stop');
        $machine = $this->createMachine($account, $location, 'soda', 'DROP-100', 'Dropdown Machine');

        $variantFamilyName = 'Variant Test Cola';

        $colaBottle = $this->createProduct($account, $variantFamilyName, '20 oz', 'Bottle');
        $colaCan = $this->createProduct($account, $variantFamilyName, '12 oz', 'Can');
        $colaLoose = $this->createProduct($account, $variantFamilyName, null, null);
        $spriteBottle = $this->createProduct($account, 'Variant Test Lemon-Lime', '20 oz', 'Bottle');

        $bin = Bin::create([
            'account_id' => $account->id,
            'machine_id' => $machine->id,
            'product_id' => $colaBottle->id,
            'bin_code' => 'A1',
            'capacity' => 10,
            'price' => 1.25,
        ]);

        $session = ['current_account_id' => $account->id];

        $createResponse = $this->actingAs($user)
            ->withSession($session)
            ->get(route('bins.create'));

        $createResponse
            ->assertOk()
            ->assertSeeText('Variant Test Cola · 12 oz · Can')
            ->assertSeeText('Variant Test Cola · 20 oz · Bottle')
            ->assertSeeText('Variant Test Cola')
            ->assertSeeText('Variant Test Lemon-Lime · 20 oz · Bottle');

        $createProducts = $createResponse->viewData('products');
        $this->assertProductBlockOrder($createProducts->pluck('id')->all(), [
            $colaCan->id,
            $colaBottle->id,
            $colaLoose->id,
        ]);

        $editResponse = $this->actingAs($user)
            ->withSession($session)
            ->get(route('bins.edit', $bin));

        $editResponse
            ->assertOk()
            ->assertSee('value="'.$colaBottle->id.'" selected', false)
            ->assertSeeText('Variant Test Cola · 20 oz · Bottle');

        $editProducts = $editResponse->viewData('products');
        $this->assertProductBlockOrder($editProducts->pluck('id')->all(), [
            $colaCan->id,
            $colaBottle->id,
            $colaLoose->id,
        ]);

        $machineEditResponse = $this->actingAs($user)
            ->withSession($session)
            ->get(route('machines.bins.edit', $machine));

        $machineEditResponse
            ->assertOk()
            ->assertSee('value="'.$colaBottle->id.'" selected', false)
            ->assertSeeText('Variant Test Cola · 12 oz · Can')
            ->assertSeeText('Variant Test Cola · 20 oz · Bottle')
            ->assertSeeText('Variant Test Lemon-Lime · 20 oz · Bottle');

        $machineEditProducts = $machineEditResponse->viewData('products');
        $this->assertProductBlockOrder($machineEditProducts->pluck('id')->all(), [
            $colaCan->id,
            $colaBottle->id,
            $colaLoose->id,
        ]);

        $this->actingAs($user)
            ->withSession($session)
            ->patch(route('bins.update', $bin), [
                'machine_id' => $machine->id,
                'product_id' => $colaCan->id,
                'bin_code' => 'A1',
                'capacity' => 10,
                'price' => 1.25,
            ])
            ->assertRedirect(route('bins.show', $bin));

        $this->assertSame($colaCan->id, $bin->fresh()->product_id);
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
            'address' => '123 Product Street',
            'city' => 'New York',
            'state' => 'NY',
            'zip_code' => '10001',
            'is_inventory' => null,
        ]);

        RouteLocation::create([
            'account_id' => $account->id,
            'route_id' => $route->id,
            'location_id' => $location->id,
            'stop_order' => (int) RouteLocation::query()
                ->where('account_id', $account->id)
                ->where('route_id', $route->id)
                ->max('stop_order') + 1,
            'is_primary' => true,
        ]);

        return $location;
    }

    protected function createMachine(Account $account, Location $location, string $type, string $serialNumber, string $model): Machine
    {
        return Machine::create([
            'account_id' => $account->id,
            'location_id' => $location->id,
            'type' => $type,
            'serial_number' => $serialNumber,
            'model' => $model,
            'status' => Machine::STATUS_ACTIVE,
            'installed_on' => null,
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
