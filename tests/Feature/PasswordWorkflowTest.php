<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_change_their_own_password_with_their_current_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('current-password'),
            'status' => User::STATUS_ACTIVE,
        ]);
        $account = $this->createAccount('Alpha Vending');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_TECHNICIAN);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->put(route('password.update'), [
                'current_password' => 'current-password',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertRedirect(route('password.edit'))
            ->assertSessionHas('status', 'Password updated successfully.');

        $user->refresh();

        $this->assertTrue(Hash::check('new-password-123', $user->password));
        $this->assertFalse(Hash::check('current-password', $user->password));
    }

    public function test_user_cannot_change_their_own_password_with_an_incorrect_current_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('current-password'),
            'status' => User::STATUS_ACTIVE,
        ]);
        $account = $this->createAccount('Beta Vending');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_TECHNICIAN);
        $originalHash = $user->password;

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->from(route('password.edit'))
            ->put(route('password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertRedirect(route('password.edit'))
            ->assertSessionHasErrors([
                'current_password' => 'The current password is incorrect.',
            ]);

        $user->refresh();

        $this->assertSame($originalHash, $user->password);
        $this->assertTrue(Hash::check('current-password', $user->password));
    }

    public function test_owner_and_admin_can_reset_another_users_password_within_the_current_account(): void
    {
        foreach ([AccountUser::ROLE_OWNER, AccountUser::ROLE_ADMIN] as $role) {
            $manager = User::factory()->create(['status' => User::STATUS_ACTIVE]);
            $target = User::factory()->create([
                'password' => Hash::make('target-password'),
                'status' => User::STATUS_ACTIVE,
            ]);
            $account = $this->createAccount(strtolower($role).' account');

            $this->attachUserToAccount($manager, $account, $role);
            $membership = $this->attachUserToAccount($target, $account, AccountUser::ROLE_TECHNICIAN);

            $this->actingAs($manager)
                ->withSession(['current_account_id' => $account->id])
                ->put(route('account-users.password.update', $membership), [
                    'password' => 'reset-password-123',
                    'password_confirmation' => 'reset-password-123',
                ])
                ->assertRedirect(route('account-users.index'))
                ->assertSessionHas('status', 'User password reset successfully.');

            $target->refresh();

            $this->assertTrue(Hash::check('reset-password-123', $target->password));
            $this->assertFalse(Hash::check('target-password', $target->password));
        }
    }

    public function test_non_admin_user_cannot_reset_another_users_password(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $target = User::factory()->create([
            'password' => Hash::make('target-password'),
            'status' => User::STATUS_ACTIVE,
        ]);
        $account = $this->createAccount('Viewer Account');

        $this->attachUserToAccount($user, $account, AccountUser::ROLE_VIEWER);
        $membership = $this->attachUserToAccount($target, $account, AccountUser::ROLE_TECHNICIAN);
        $originalHash = $target->password;

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('account-users.password.edit', $membership))
            ->assertForbidden();

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->put(route('account-users.password.update', $membership), [
                'password' => 'reset-password-123',
                'password_confirmation' => 'reset-password-123',
            ])
            ->assertForbidden();

        $target->refresh();

        $this->assertSame($originalHash, $target->password);
    }

    public function test_password_reset_cannot_cross_accounts_by_changing_the_url(): void
    {
        $manager = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $target = User::factory()->create([
            'password' => Hash::make('target-password'),
            'status' => User::STATUS_ACTIVE,
        ]);
        $accountA = $this->createAccount('Account A');
        $accountB = $this->createAccount('Account B');

        $this->attachUserToAccount($manager, $accountA, AccountUser::ROLE_OWNER);
        $membership = $this->attachUserToAccount($target, $accountB, AccountUser::ROLE_TECHNICIAN);
        $originalHash = $target->password;

        $this->actingAs($manager)
            ->withSession(['current_account_id' => $accountA->id])
            ->get(route('account-users.password.edit', $membership))
            ->assertNotFound();

        $this->actingAs($manager)
            ->withSession(['current_account_id' => $accountA->id])
            ->put(route('account-users.password.update', $membership), [
                'password' => 'reset-password-123',
                'password_confirmation' => 'reset-password-123',
            ])
            ->assertNotFound();

        $target->refresh();

        $this->assertSame($originalHash, $target->password);
    }

    public function test_owner_cannot_use_admin_reset_flow_on_their_own_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('current-password'),
            'status' => User::STATUS_ACTIVE,
        ]);
        $account = $this->createAccount('Self Reset Guard');
        $membership = $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);
        $originalHash = $user->password;

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('account-users.password.edit', $membership))
            ->assertForbidden();

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->put(route('account-users.password.update', $membership), [
                'password' => 'reset-password-123',
                'password_confirmation' => 'reset-password-123',
            ])
            ->assertForbidden();

        $user->refresh();

        $this->assertSame($originalHash, $user->password);
    }

    protected function createAccount(string $name): Account
    {
        return Account::create([
            'account_name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)).'-'.uniqid(),
            'status' => 'active',
            'billing_email' => strtolower(str_replace(' ', '.', $name)).'@example.com',
        ]);
    }

    protected function attachUserToAccount(User $user, Account $account, string $role): AccountUser
    {
        return AccountUser::create([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => AccountUser::STATUS_ACTIVE,
        ]);
    }
}
