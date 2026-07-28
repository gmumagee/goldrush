<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class AdminAccountBlockingTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_block_an_active_account_and_unblock_a_blocked_one(): void
    {
        $user = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
            'is_super_admin' => true,
        ]);

        $account = $this->createAccount('Blockable Account');

        $this->actingAs($user)
            ->post(route('admin.accounts.block', $account))
            ->assertRedirect(route('admin.accounts.index'))
            ->assertSessionHas('status', 'Account blocked successfully.');

        $this->assertSame(Account::STATUS_INACTIVE, $account->fresh()->status);

        $this->actingAs($user)
            ->post(route('admin.accounts.unblock', $account))
            ->assertRedirect(route('admin.accounts.index'))
            ->assertSessionHas('status', 'Account unblocked successfully.');

        $this->assertSame(Account::STATUS_ACTIVE, $account->fresh()->status);
    }

    public function test_blocked_account_user_is_denied_account_scoped_routes_and_sees_suspension_message(): void
    {
        $user = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
            'is_super_admin' => false,
        ]);

        $account = $this->createAccount('Suspended Account');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);

        $account->forceFill(['status' => Account::STATUS_INACTIVE])->save();

        $response = $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->followingRedirects()
            ->get(route('products.index'));

        $response->assertOk()
            ->assertSessionMissing('current_account_id')
            ->assertSeeText('This account has been suspended.');
    }

    public function test_super_admin_cannot_block_the_currently_selected_account(): void
    {
        $user = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
            'is_super_admin' => true,
        ]);

        $account = $this->createAccount('Selected Account');

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('admin.accounts.block', $account))
            ->assertRedirect(route('admin.accounts.index'))
            ->assertSessionHasErrors([
                'account' => 'You cannot block the account currently selected in your session.',
            ]);

        $this->assertSame(Account::STATUS_ACTIVE, $account->fresh()->status);
    }

    public function test_non_super_admin_gets_forbidden_on_block_and_unblock_actions(): void
    {
        $user = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
            'is_super_admin' => false,
        ]);

        $account = $this->createAccount('Owned Account');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);

        $this->actingAs($user)
            ->post(route('admin.accounts.block', $account))
            ->assertForbidden();

        $account->forceFill(['status' => Account::STATUS_INACTIVE])->save();

        $this->actingAs($user)
            ->post(route('admin.accounts.unblock', $account))
            ->assertForbidden();
    }

    public function test_block_and_unblock_write_audit_log_entries_attributed_to_the_super_admin(): void
    {
        $user = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
            'is_super_admin' => true,
        ]);

        $account = $this->createAccount('Audited Account');

        $this->actingAs($user)->post(route('admin.accounts.block', $account));
        $this->actingAs($user)->post(route('admin.accounts.unblock', $account));

        $entries = AuditLog::query()
            ->where('account_id', $account->id)
            ->where('user_id', $user->id)
            ->where('auditable_type', Account::class)
            ->where('auditable_id', $account->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $entries);
        $this->assertSame(AuditLog::EVENT_UPDATED, $entries[0]->event);
        $this->assertSame('blocked', $entries[0]->changes['operator_action'] ?? null);
        $this->assertSame(Account::STATUS_ACTIVE, $entries[0]->changes['status']['old'] ?? null);
        $this->assertSame(Account::STATUS_INACTIVE, $entries[0]->changes['status']['new'] ?? null);
        $this->assertSame('unblocked', $entries[1]->changes['operator_action'] ?? null);
        $this->assertSame(Account::STATUS_INACTIVE, $entries[1]->changes['status']['old'] ?? null);
        $this->assertSame(Account::STATUS_ACTIVE, $entries[1]->changes['status']['new'] ?? null);
    }

    public function test_admin_accounts_area_and_block_actions_are_unavailable_in_single_tenant_mode(): void
    {
        Config::set('tenancy.mode', Tenancy::MODE_SINGLE);
        Config::set('tenancy.single_tenant_account_id', 1);

        $user = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
            'is_super_admin' => true,
        ]);

        $account = $this->createAccount('Single Account', 1);

        $this->actingAs($user)
            ->get(route('admin.accounts.index'))
            ->assertNotFound();

        $this->actingAs($user)
            ->post(route('admin.accounts.block', $account))
            ->assertNotFound();
    }

    protected function createAccount(string $name, ?int $id = null): Account
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
