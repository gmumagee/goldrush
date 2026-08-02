<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\AuditLog;
use App\Models\Expense;
use App\Models\Location;
use App\Models\RouteLocation;
use App\Models\User;
use App\Models\VendingRoute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_general_and_location_tied_expenses_saves_correctly(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Expense Create Account');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);
        $route = $this->createRoute($account, 'Expense Route');
        $location = $this->createLocation($account, $route, 'Expense Stop');

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('expenses.store'), [
                'location_id' => '',
                'category' => Expense::CATEGORY_FUEL,
                'amount' => '42.15',
                'expense_date' => '2026-08-02',
                'vendor' => 'Shell',
                'description' => 'Fuel for route day',
            ])
            ->assertRedirect(route('expenses.index'))
            ->assertSessionHas('status', 'Expense created successfully.');

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('expenses.store'), [
                'location_id' => (string) $location->id,
                'category' => Expense::CATEGORY_MAINTENANCE,
                'amount' => '15.50',
                'expense_date' => '2026-08-01',
                'vendor' => 'FixIt Supply',
                'description' => 'Machine parts',
            ])
            ->assertRedirect(route('expenses.index'))
            ->assertSessionHas('status', 'Expense created successfully.');

        $this->assertDatabaseHas('tbl_expenses', [
            'account_id' => $account->id,
            'location_id' => null,
            'category' => Expense::CATEGORY_FUEL,
            'amount' => 42.15,
            'expense_date' => '2026-08-02 00:00:00',
            'vendor' => 'Shell',
            'created_by_user_id' => $user->id,
        ]);
        $this->assertDatabaseHas('tbl_expenses', [
            'account_id' => $account->id,
            'location_id' => $location->id,
            'category' => Expense::CATEGORY_MAINTENANCE,
            'amount' => 15.50,
            'expense_date' => '2026-08-01 00:00:00',
            'vendor' => 'FixIt Supply',
            'created_by_user_id' => $user->id,
        ]);
    }

    public function test_invalid_category_is_rejected(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Expense Invalid Category');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->from(route('expenses.create'))
            ->post(route('expenses.store'), [
                'location_id' => '',
                'category' => 'invalid-category',
                'amount' => '19.99',
                'expense_date' => '2026-08-02',
                'vendor' => '',
                'description' => '',
            ])
            ->assertRedirect(route('expenses.create'))
            ->assertSessionHasErrors('category');
    }

    public function test_manage_roles_can_create_and_delete_while_technician_and_viewer_cannot(): void
    {
        $account = $this->createAccount('Expense Permissions');
        $route = $this->createRoute($account, 'Expense Permissions Route');
        $location = $this->createLocation($account, $route, 'Expense Permissions Stop');

        foreach ([AccountUser::ROLE_OWNER, AccountUser::ROLE_ADMIN, AccountUser::ROLE_MANAGER] as $role) {
            $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
            $this->attachUserToAccount($user, $account, $role);

            $this->actingAs($user)
                ->withSession(['current_account_id' => $account->id])
                ->post(route('expenses.store'), [
                    'location_id' => (string) $location->id,
                    'category' => Expense::CATEGORY_SUPPLIES,
                    'amount' => '9.99',
                    'expense_date' => '2026-08-02',
                    'vendor' => 'Office Depot',
                    'description' => 'Labels',
                ])
                ->assertRedirect(route('expenses.index'));
        }

        $expense = Expense::query()->where('account_id', $account->id)->latest('id')->firstOrFail();

        $manager = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $this->attachUserToAccount($manager, $account, AccountUser::ROLE_MANAGER);

        $this->actingAs($manager)
            ->withSession(['current_account_id' => $account->id])
            ->delete(route('expenses.destroy', $expense))
            ->assertRedirect(route('expenses.index'))
            ->assertSessionHas('status', 'Expense deleted successfully.');

        $expense = $this->createExpense($account, [
            'location_id' => $location->id,
            'category' => Expense::CATEGORY_OTHER,
            'amount' => 12.34,
            'expense_date' => '2026-08-02',
            'vendor' => null,
            'description' => null,
            'created_by_user_id' => $manager->id,
        ]);

        foreach ([AccountUser::ROLE_TECHNICIAN, AccountUser::ROLE_VIEWER] as $role) {
            $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
            $this->attachUserToAccount($user, $account, $role);

            $this->actingAs($user)
                ->withSession(['current_account_id' => $account->id])
                ->post(route('expenses.store'), [
                    'location_id' => '',
                    'category' => Expense::CATEGORY_FUEL,
                    'amount' => '19.99',
                    'expense_date' => '2026-08-02',
                ])
                ->assertForbidden();

            $this->actingAs($user)
                ->withSession(['current_account_id' => $account->id])
                ->patch(route('expenses.update', $expense), [
                    'location_id' => '',
                    'category' => Expense::CATEGORY_FUEL,
                    'amount' => '21.00',
                    'expense_date' => '2026-08-02',
                ])
                ->assertForbidden();

            $this->actingAs($user)
                ->withSession(['current_account_id' => $account->id])
                ->delete(route('expenses.destroy', $expense))
                ->assertForbidden();
        }
    }

    public function test_deleting_a_location_nulls_out_historical_expense_location_ids(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Expense Null Location');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);

        $location = Location::create([
            'account_id' => $account->id,
            'location_name' => 'Delete Me',
            'address' => null,
            'city' => null,
            'state' => null,
            'zip_code' => null,
            'is_inventory' => null,
        ]);

        $expense = $this->createExpense($account, [
            'location_id' => $location->id,
            'category' => Expense::CATEGORY_RENT,
            'amount' => 500.00,
            'expense_date' => '2026-08-02',
            'vendor' => 'Landlord',
            'description' => 'Monthly site rent',
            'created_by_user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->delete(route('locations.destroy', $location))
            ->assertRedirect(route('locations.index'));

        $this->assertDatabaseHas('tbl_expenses', [
            'id' => $expense->id,
            'location_id' => null,
        ]);
    }

    public function test_expenses_are_account_scoped_and_filters_work(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $accountA = $this->createAccount('Expense Account A');
        $accountB = $this->createAccount('Expense Account B');
        $this->attachUserToAccount($user, $accountA, AccountUser::ROLE_OWNER);

        $route = $this->createRoute($accountA, 'Filter Route');
        $locationA = $this->createLocation($accountA, $route, 'Alpha Stop');
        $otherRoute = $this->createRoute($accountB, 'Foreign Route');
        $locationB = $this->createLocation($accountB, $otherRoute, 'Foreign Stop');

        $generalExpense = $this->createExpense($accountA, [
            'location_id' => null,
            'category' => Expense::CATEGORY_FUEL,
            'amount' => 25.00,
            'expense_date' => '2026-08-01',
            'vendor' => 'Fuel Station',
            'description' => 'General fuel expense',
            'created_by_user_id' => $user->id,
        ]);
        $locationExpense = $this->createExpense($accountA, [
            'location_id' => $locationA->id,
            'category' => Expense::CATEGORY_MAINTENANCE,
            'amount' => 40.00,
            'expense_date' => '2026-08-02',
            'vendor' => 'Parts House',
            'description' => 'Alpha stop maintenance',
            'created_by_user_id' => $user->id,
        ]);
        $foreignExpense = $this->createExpense($accountB, [
            'location_id' => $locationB->id,
            'category' => Expense::CATEGORY_RENT,
            'amount' => 99.00,
            'expense_date' => '2026-08-02',
            'vendor' => 'Foreign Vendor',
            'description' => 'Foreign expense',
            'created_by_user_id' => $user->id,
        ]);

        $indexResponse = $this->actingAs($user)
            ->withSession(['current_account_id' => $accountA->id])
            ->get(route('expenses.index'));

        $indexResponse
            ->assertOk()
            ->assertSeeText('General fuel expense')
            ->assertSeeText('Alpha stop maintenance')
            ->assertDontSeeText('Foreign expense');

        $filteredByLocation = $this->actingAs($user)
            ->withSession(['current_account_id' => $accountA->id])
            ->get(route('expenses.index', ['location_filter' => (string) $locationA->id]));

        $filteredByLocation
            ->assertOk()
            ->assertSeeText('Alpha stop maintenance')
            ->assertDontSeeText('General fuel expense');

        $filteredByCategory = $this->actingAs($user)
            ->withSession(['current_account_id' => $accountA->id])
            ->get(route('expenses.index', ['category' => Expense::CATEGORY_FUEL]));

        $filteredByCategory
            ->assertOk()
            ->assertSeeText('General fuel expense')
            ->assertDontSeeText('Alpha stop maintenance');

        $filteredByDate = $this->actingAs($user)
            ->withSession(['current_account_id' => $accountA->id])
            ->get(route('expenses.index', ['date_from' => '2026-08-02', 'date_to' => '2026-08-02']));

        $filteredByDate
            ->assertOk()
            ->assertSeeText('Alpha stop maintenance')
            ->assertDontSeeText('General fuel expense');

        $filteredByDate
            ->assertSee('type="date"', false)
            ->assertSee('value="2026-08-02"', false);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $accountA->id])
            ->get(route('expenses.edit', $foreignExpense))
            ->assertNotFound();

        $this->assertTrue($generalExpense->exists);
        $this->assertTrue($locationExpense->exists);
    }

    public function test_expense_changes_are_written_to_the_audit_log(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Expense Audit Account');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);

        $expense = null;

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id]);

        $expense = $this->createExpense($account, [
            'location_id' => null,
            'category' => Expense::CATEGORY_OTHER,
            'amount' => 10.00,
            'expense_date' => '2026-08-02',
            'vendor' => 'Test Vendor',
            'description' => 'Initial',
            'created_by_user_id' => $user->id,
        ]);

        $expense->update([
            'amount' => 11.50,
            'description' => 'Updated',
        ]);

        $expenseId = $expense->id;
        $expense->delete();

        $entries = AuditLog::query()
            ->where('account_id', $account->id)
            ->where('auditable_type', Expense::class)
            ->where('auditable_id', $expenseId)
            ->orderBy('id')
            ->get();

        $this->assertSame(['created', 'updated', 'deleted'], $entries->pluck('event')->all());
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

    protected function createLocation(Account $account, VendingRoute $route, string $name): Location
    {
        $location = Location::create([
            'account_id' => $account->id,
            'location_name' => $name,
            'address' => '123 Main Street',
            'city' => 'Toronto',
            'state' => 'ON',
            'zip_code' => 'M1M1M1',
            'is_inventory' => null,
        ]);

        RouteLocation::create([
            'account_id' => $account->id,
            'route_id' => $route->id,
            'location_id' => $location->id,
            'stop_order' => 1,
            'is_primary' => true,
        ]);

        return $location;
    }

    protected function createExpense(Account $account, array $attributes): Expense
    {
        $expense = new Expense([
            'location_id' => $attributes['location_id'] ?? null,
            'category' => $attributes['category'] ?? Expense::CATEGORY_OTHER,
            'amount' => $attributes['amount'] ?? 10.00,
            'expense_date' => $attributes['expense_date'] ?? '2026-08-02',
            'vendor' => $attributes['vendor'] ?? null,
            'description' => $attributes['description'] ?? null,
        ]);

        $expense->account_id = $account->id;
        $expense->created_by_user_id = $attributes['created_by_user_id'] ?? null;
        $expense->save();

        return $expense;
    }
}
