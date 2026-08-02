<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductReorderPointTest extends TestCase
{
    use RefreshDatabase;

    public function test_reorder_point_column_exists_and_products_default_it_to_null(): void
    {
        $this->assertTrue(Schema::hasColumn('tbl_products', 'reorder_point'));

        $account = $this->createAccount('Product Reorder Column Account');
        $product = Product::create([
            'account_id' => $account->id,
            'vendor_id' => null,
            'category' => 'Snack',
            'brand' => 'Brand',
            'sku' => 'COL-NULL-100',
            'product_name' => 'Column Check Product',
            'size' => null,
            'package_type' => null,
            'barcode' => null,
        ]);

        $this->assertNull($product->fresh()->reorder_point);
    }

    public function test_create_and_update_allow_optional_reorder_point_and_show_it_on_the_detail_page(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Product Reorder Account');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('products.store'), [
                'vendor_id' => '',
                'category' => 'Beverage',
                'brand' => 'Acme',
                'sku' => 'REORDER-100',
                'product_name' => 'Route Cola',
                'size' => '20 oz',
                'package_type' => 'Bottle',
                'barcode' => '111222333',
                'reorder_point' => '12',
            ])
            ->assertRedirect(route('products.index'))
            ->assertSessionHas('status', 'Product created successfully.');

        $product = Product::query()
            ->where('account_id', $account->id)
            ->where('sku', 'REORDER-100')
            ->firstOrFail();

        $this->assertSame(12, $product->reorder_point);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('products.show', $product))
            ->assertOk()
            ->assertSeeText('Reorder Point')
            ->assertSeeText('12 units');

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->patch(route('products.update', $product), [
                'vendor_id' => '',
                'category' => 'Beverage',
                'brand' => 'Acme',
                'sku' => 'REORDER-100',
                'product_name' => 'Route Cola',
                'size' => '20 oz',
                'package_type' => 'Bottle',
                'barcode' => '111222333',
                'reorder_point' => '',
            ])
            ->assertRedirect(route('products.show', $product))
            ->assertSessionHas('status', 'Product updated successfully.');

        $this->assertNull($product->fresh()->reorder_point);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('products.show', $product))
            ->assertOk()
            ->assertSeeText('Reorder Point')
            ->assertSeeText('Not set');
    }

    public function test_reorder_point_must_be_a_non_negative_integer_when_present(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Product Reorder Validation Account');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->from(route('products.create'))
            ->post(route('products.store'), [
                'vendor_id' => '',
                'category' => 'Snack',
                'brand' => 'Acme',
                'sku' => 'REORDER-NEG',
                'product_name' => 'Invalid Product',
                'size' => '',
                'package_type' => '',
                'barcode' => '',
                'reorder_point' => '-1',
            ])
            ->assertRedirect(route('products.create'))
            ->assertSessionHasErrors('reorder_point');
    }

    protected function createAccount(string $name): Account
    {
        return Account::create([
            'account_name' => $name,
            'account_number' => 'ACC-'.strtoupper(substr(md5($name), 0, 8)),
            'slug' => strtolower(str_replace(' ', '-', $name)).'-'.substr(md5($name), 0, 6),
            'is_active' => true,
        ]);
    }

    protected function attachUserToAccount(User $user, Account $account, string $role): void
    {
        AccountUser::create([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'role' => $role,
            'is_active' => true,
        ]);
    }
}
