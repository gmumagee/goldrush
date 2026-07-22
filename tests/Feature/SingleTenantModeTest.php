<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class SingleTenantModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_mode_auto_assigns_the_configured_account_without_redirecting(): void
    {
        Config::set('tenancy.mode', Tenancy::MODE_SINGLE);
        Config::set('tenancy.single_tenant_account_id', 1);

        $account = $this->createAccount('Single Account', 1);
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);

        $response = $this->actingAs($user)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertSessionHas('current_account_id', 1);
    }

    public function test_single_mode_redirects_account_selection_away_and_hides_switch_account_ui(): void
    {
        Config::set('tenancy.mode', Tenancy::MODE_SINGLE);
        Config::set('tenancy.single_tenant_account_id', 1);

        $account = $this->createAccount('Single Account', 1);
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);

        $this->actingAs($user)
            ->get(route('accounts.select'))
            ->assertRedirect('/dashboard');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSeeText('Switch Account');
    }

    public function test_single_mode_first_registration_creates_the_configured_account_without_account_name_input(): void
    {
        Config::set('tenancy.mode', Tenancy::MODE_SINGLE);
        Config::set('tenancy.single_tenant_account_id', 1);

        $this->get(route('register'))
            ->assertOk()
            ->assertDontSeeText('Business / account name');

        $response = $this->post(route('register'), [
            'name' => 'Single Owner',
            'email' => 'owner@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect('/dashboard');

        $this->assertDatabaseCount('tbl_accounts', 1);
        $this->assertDatabaseHas('tbl_accounts', [
            'id' => 1,
            'account_name' => config('app.name', 'GoldRush'),
        ]);
        $this->assertDatabaseHas('tbl_account_users', [
            'account_id' => 1,
            'role' => AccountUser::ROLE_OWNER,
            'status' => AccountUser::STATUS_ACTIVE,
        ]);
        $this->assertAuthenticated();
    }

    public function test_single_mode_registration_does_not_create_a_second_account(): void
    {
        Config::set('tenancy.mode', Tenancy::MODE_SINGLE);
        Config::set('tenancy.single_tenant_account_id', 1);

        $this->createAccount('Single Account', 1);

        $response = $this->post(route('register'), [
            'name' => 'Second User',
            'email' => 'second@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');

        $this->assertDatabaseCount('tbl_accounts', 1);
        $this->assertDatabaseCount('tbl_users', 0);
    }

    public function test_multi_mode_still_requires_account_selection_and_shows_switch_account_ui(): void
    {
        Config::set('tenancy.mode', Tenancy::MODE_MULTI);

        $account = $this->createAccount('Multi Account');
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('accounts.select'));

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText('Switch Account');
    }

    public function test_multi_mode_registration_still_creates_a_new_account(): void
    {
        Config::set('tenancy.mode', Tenancy::MODE_MULTI);

        $this->get(route('register'))
            ->assertOk()
            ->assertSeeText('Business / account name');

        $response = $this->post(route('register'), [
            'name' => 'Multi Owner',
            'email' => 'multi@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'account_name' => 'Multi Tenant Account',
        ]);

        $response->assertRedirect('/dashboard');

        $this->assertDatabaseCount('tbl_accounts', 1);
        $this->assertDatabaseHas('tbl_accounts', [
            'account_name' => 'Multi Tenant Account',
        ]);
    }

    private function createAccount(string $name, ?int $id = null): Account
    {
        $account = new Account();
        $account->forceFill([
            'id' => $id,
            'account_name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)).'-'.uniqid(),
            'status' => Account::STATUS_ACTIVE,
            'billing_email' => strtolower(str_replace(' ', '.', $name)).'@example.com',
        ]);
        $account->save();

        return $account;
    }

    private function attachUserToAccount(User $user, Account $account, string $role): void
    {
        AccountUser::create([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => AccountUser::STATUS_ACTIVE,
        ]);
    }
}
