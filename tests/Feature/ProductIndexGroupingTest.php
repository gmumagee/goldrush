<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductIndexGroupingTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_index_groups_products_by_category_and_buckets_uncategorized(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Grouped Products Account');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);

        Product::create([
            'account_id' => $account->id,
            'category' => 'Soda',
            'brand' => 'Coca-Cola',
            'sku' => 'COKE-20',
            'product_name' => 'Coca-Cola 20 oz',
        ]);
        Product::create([
            'account_id' => $account->id,
            'category' => 'Candy',
            'brand' => 'Mars',
            'sku' => 'SNICKERS',
            'product_name' => 'Snickers',
        ]);
        Product::create([
            'account_id' => $account->id,
            'category' => 'Soda',
            'brand' => 'Pepsi',
            'sku' => 'PEPSI-20',
            'product_name' => 'Pepsi 20 oz',
        ]);
        Product::create([
            'account_id' => $account->id,
            'category' => '',
            'brand' => 'House',
            'sku' => 'MISC-1',
            'product_name' => 'Mystery Item',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('products.index'));

        $response->assertOk()
            ->assertSeeText('Expand all')
            ->assertSeeText('Collapse all')
            ->assertDontSeeText('No products found for this account.');

        $productsByCategory = $response->viewData('productsByCategory');

        $this->assertSame(['Candy', 'Soda', 'Uncategorized'], $productsByCategory->keys()->all());
        $this->assertSame(['Coca-Cola 20 oz', 'Pepsi 20 oz'], $productsByCategory->get('Soda')->pluck('product_name')->all());
        $this->assertSame(['Mystery Item'], $productsByCategory->get('Uncategorized')->pluck('product_name')->all());
    }

    public function test_products_index_search_keeps_existing_filter_logic_and_limits_grouped_results(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Searched Products Account');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);

        Product::create([
            'account_id' => $account->id,
            'category' => 'Soda',
            'brand' => 'Pepsi',
            'sku' => 'PEPSI-20',
            'product_name' => 'Pepsi 20 oz',
            'barcode' => '111',
        ]);
        Product::create([
            'account_id' => $account->id,
            'category' => 'Candy',
            'brand' => 'Mars',
            'sku' => 'SNICKERS',
            'product_name' => 'Snickers',
            'barcode' => '222',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('products.index', ['search' => 'Pepsi']));

        $response->assertOk()
            ->assertSee('x-data="{ open: true }"', false)
            ->assertSeeText('Soda')
            ->assertDontSeeText('Candy');

        $productsByCategory = $response->viewData('productsByCategory');

        $this->assertSame('Pepsi', $response->viewData('search'));
        $this->assertSame(['Soda'], $productsByCategory->keys()->all());
        $this->assertSame(['Pepsi 20 oz'], $productsByCategory->get('Soda')->pluck('product_name')->all());
    }

    protected function createAccount(string $name): Account
    {
        return Account::withoutEvents(fn () => Account::create([
            'account_name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)).'-'.uniqid(),
            'status' => Account::STATUS_ACTIVE,
            'billing_email' => strtolower(str_replace(' ', '.', $name)).'@example.com',
        ]));
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
}
