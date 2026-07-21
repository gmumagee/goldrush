<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_sidebar_keeps_group_order_and_alphabetized_children(): void
    {
        // Render the shared sidebar through a real page so ordering assertions match the final HTML.
        $user = User::factory()->create(['status' => 'active']);
        $account = $this->createAccount('Sidebar Account');
        $this->attachUserToAccount($user, $account, 'owner');

        $response = $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('dashboard'));

        $response->assertOk();

        $content = $response->getContent();
        $routeManagementSection = $this->extractSidebarSection($content, 'sidebar-route-management');
        $inventorySection = $this->extractSidebarSection($content, 'sidebar-inventory');
        $operationsSection = $this->extractSidebarSection($content, 'sidebar-operations');
        $accountSection = $this->extractSidebarSection($content, 'sidebar-account');

        $this->assertStringOrder($content, [
            'Workspace',
            'aria-controls="sidebar-operations"',
            'aria-controls="sidebar-route-management"',
            'aria-controls="sidebar-inventory"',
            'aria-controls="sidebar-account"',
        ]);
        $this->assertStringOrder($operationsSection, ['Calendar', 'Services']);
        $this->assertStringOrder($routeManagementSection, ['Locations', 'Machines', 'Routes']);
        $this->assertStringOrder($inventorySection, ['Products', 'Purchases', 'Transactions', 'Vendors', 'Warehouses']);
        $this->assertStringOrder($accountSection, ['Change Password', 'Contacts', 'Data Dictionary', 'Settings', 'Switch Account', 'Users']);

        $this->assertSame(1, substr_count($content, 'Route Management'));
        $this->assertSame(1, substr_count($content, route('routes.index')));
        $this->assertSame(1, substr_count($content, route('transactions.index')));
        $this->assertStringNotContainsString('>Bins<', $content);
        $this->assertStringNotContainsString('href="'.route('bins.index').'"', $content);
        $this->assertStringNotContainsString('Inventory Setup', $content);
        $this->assertStringNotContainsString('<span class="truncate leading-5">Account</span>', $content);
        $this->assertStringContainsString(
            'href="'.route('transactions.index').'"',
            $inventorySection
        );
        $this->assertStringNotContainsString(
            'href="'.route('transactions.index').'"',
            $operationsSection
        );
        $this->assertStringContainsString(
            'href="'.route('routes.index').'"',
            $routeManagementSection
        );

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('bins.index'))
            ->assertOk();
    }

    public function test_route_pages_expand_route_management_without_expanding_operations(): void
    {
        // Keep route-management visibility tied to its own top-level group so route pages open the correct section.
        $user = User::factory()->create(['status' => 'active']);
        $account = $this->createAccount('Routes Sidebar Account');
        $this->attachUserToAccount($user, $account, 'owner');

        $response = $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('routes.index'));

        $response->assertOk();

        $content = $response->getContent();

        $this->assertStringContainsString(
            "open: false,\n                    init() {\n                        const saved = localStorage.getItem('sidebar-operations-open');",
            $content
        );
        $this->assertStringContainsString(
            "open: true,\n                    init() {\n                        const saved = localStorage.getItem('sidebar-route-management-open');",
            $content
        );
        $this->assertStringContainsString(
            'href="'.route('routes.index').'" class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition bg-violet-500/10 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300"',
            $content
        );
        $this->assertStringContainsString(
            'href="'.route('routes.index').'" class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition bg-violet-500/10 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300"',
            $this->extractSidebarSection($content, 'sidebar-route-management')
        );
        $this->assertStringNotContainsString(
            'href="'.route('routes.index').'"',
            $this->extractSidebarSection($content, 'sidebar-operations')
        );
    }

    public function test_transactions_page_keeps_transactions_nested_under_inventory_and_inventory_open(): void
    {
        // Preserve the previous inventory placement so transaction routes still open the inventory group.
        $user = User::factory()->create(['status' => 'active']);
        $account = $this->createAccount('Transactions Sidebar Account');
        $this->attachUserToAccount($user, $account, 'owner');

        $response = $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('transactions.index'));

        $response->assertOk();

        $content = $response->getContent();

        $this->assertStringContainsString(
            "open: true,\n                    init() {\n                        const saved = localStorage.getItem('sidebar-inventory-open');",
            $content
        );
        $this->assertStringContainsString(
            "open: false,\n                    init() {\n                        const saved = localStorage.getItem('sidebar-operations-open');",
            $content
        );
        $this->assertStringContainsString(
            'href="'.route('transactions.index').'" class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition bg-violet-500/10 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300"',
            $content
        );
        $this->assertStringContainsString(
            'href="'.route('transactions.index').'" class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition bg-violet-500/10 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300"',
            $this->extractSidebarSection($content, 'sidebar-inventory')
        );
        $this->assertStringNotContainsString(
            'href="'.route('transactions.index').'"',
            $this->extractSidebarSection($content, 'sidebar-operations')
        );
    }

    protected function createAccount(string $name): Account
    {
        // Keep test account setup local so sidebar coverage stays independent from unrelated helpers.
        return Account::create([
            'account_name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)).'-'.uniqid(),
            'status' => 'active',
            'billing_email' => strtolower(str_replace(' ', '.', $name)).'@example.com',
        ]);
    }

    protected function attachUserToAccount(User $user, Account $account, string $role): void
    {
        // Attach the signed-in user to the current account so authenticated navigation can render normally.
        AccountUser::create([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => 'active',
        ]);
    }

    protected function extractSidebarSection(string $content, string $sectionId): string
    {
        // Scope sidebar link assertions to one rendered section so later groups do not satisfy broad page-level regex checks.
        preg_match('/<ul id="'.preg_quote($sectionId, '/').'"[^>]*>.*?<\/ul>/s', $content, $matches);

        return $matches[0] ?? '';
    }

    protected function assertStringOrder(string $haystack, array $needles): void
    {
        $offset = 0;

        foreach ($needles as $needle) {
            $position = strpos($haystack, $needle, $offset);

            $this->assertNotFalse($position, sprintf('Failed to find "%s" after offset %d.', $needle, $offset));

            $offset = $position + strlen($needle);
        }
    }
}
