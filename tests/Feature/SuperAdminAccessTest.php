<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\SuperAdminAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_super_admin_without_membership_is_still_blocked_from_foreign_account_routes(): void
    {
        $user = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
            'is_super_admin' => false,
        ]);

        $account = $this->createAccount('Foreign Account');

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('products.index'))
            ->assertRedirect(route('accounts.select'));
    }

    public function test_super_admin_without_membership_can_access_account_scoped_routes_and_is_audited(): void
    {
        $user = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
            'is_super_admin' => true,
        ]);

        $account = $this->createAccount('Bypassed Account');

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('products.index'))
            ->assertOk();

        $this->assertDatabaseHas('tbl_super_admin_audit_log', [
            'user_id' => $user->id,
            'account_id' => $account->id,
            'action' => 'viewed_account',
        ]);
    }

    public function test_non_super_admin_is_forbidden_from_platform_admin_routes(): void
    {
        $user = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
            'is_super_admin' => false,
        ]);

        $account = $this->createAccount('Owned Account');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);

        $this->actingAs($user)
            ->get(route('admin.accounts.index'))
            ->assertForbidden();
    }

    public function test_super_admin_can_view_platform_account_index_without_account_membership(): void
    {
        $user = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
            'is_super_admin' => true,
        ]);

        $firstAccount = $this->createAccount('Alpha Account');
        $secondAccount = $this->createAccount('Beta Account');

        $response = $this->actingAs($user)
            ->get(route('admin.accounts.index'));

        $response->assertOk()
            ->assertSeeText('Platform Accounts')
            ->assertSeeText('Alpha Account')
            ->assertSeeText('Beta Account')
            ->assertSeeText('#'.$firstAccount->id)
            ->assertSeeText('#'.$secondAccount->id);
    }

    public function test_grant_and_revoke_super_admin_commands_update_the_user_by_email(): void
    {
        $user = User::factory()->create([
            'email' => 'operator@example.com',
            'is_super_admin' => false,
        ]);

        $this->artisan('admin:grant-super-admin', ['email' => $user->email])
            ->expectsOutput('Granted super-admin access to operator@example.com.')
            ->assertExitCode(0);

        $this->assertTrue($user->fresh()->isSuperAdmin());

        $this->artisan('admin:revoke-super-admin', ['email' => $user->email])
            ->expectsOutput('Revoked super-admin access from operator@example.com.')
            ->assertExitCode(0);

        $this->assertFalse($user->fresh()->isSuperAdmin());
    }

    public function test_super_admin_commands_fail_cleanly_for_unknown_email(): void
    {
        $this->artisan('admin:grant-super-admin', ['email' => 'missing@example.com'])
            ->expectsOutput('User not found for that email address.')
            ->assertExitCode(1);

        $this->artisan('admin:revoke-super-admin', ['email' => 'missing@example.com'])
            ->expectsOutput('User not found for that email address.')
            ->assertExitCode(1);
    }

    private function createAccount(string $name): Account
    {
        return Account::create([
            'account_name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)).'-'.uniqid(),
            'status' => Account::STATUS_ACTIVE,
            'billing_email' => strtolower(str_replace(' ', '.', $name)).'@example.com',
        ]);
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
