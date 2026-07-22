<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\AuditLog;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_and_admin_can_view_only_their_current_account_audit_entries(): void
    {
        foreach ([AccountUser::ROLE_OWNER, AccountUser::ROLE_ADMIN] as $role) {
            $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
            $account = $this->createAccount('Primary Account '.$role);
            $otherAccount = $this->createAccount('Other Account '.$role);

            $this->attachUserToAccount($user, $account, $role);

            AuditLog::create([
                'account_id' => $account->id,
                'user_id' => $user->id,
                'auditable_type' => Service::class,
                'auditable_id' => 101,
                'event' => AuditLog::EVENT_UPDATED,
                'changes' => ['status' => ['old' => 'Awaiting Service', 'new' => 'Service Open']],
                'created_at' => now(),
            ]);

            AuditLog::create([
                'account_id' => $otherAccount->id,
                'user_id' => $user->id,
                'auditable_type' => Service::class,
                'auditable_id' => 202,
                'event' => AuditLog::EVENT_UPDATED,
                'changes' => ['status' => ['old' => 'Posted', 'new' => 'Voided']],
                'created_at' => now()->subMinute(),
            ]);

            $response = $this->actingAs($user)
                ->withSession(['current_account_id' => $account->id])
                ->get(route('audit-log.index'));

            $auditEntries = $response->viewData('auditEntries');

            $response->assertOk()
                ->assertSeeText('Audit Log')
                ->assertSeeText('Service Open')
                ->assertSeeText('#101');

            $this->assertSame([$account->id], $auditEntries->getCollection()->pluck('account_id')->unique()->values()->all());
            $this->assertSame([101], $auditEntries->getCollection()->pluck('auditable_id')->all());
        }
    }

    public function test_manager_technician_and_viewer_receive_forbidden(): void
    {
        foreach ([AccountUser::ROLE_MANAGER, AccountUser::ROLE_TECHNICIAN, AccountUser::ROLE_VIEWER] as $role) {
            $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
            $account = $this->createAccount('Restricted '.$role);

            $this->attachUserToAccount($user, $account, $role);

            $this->actingAs($user)
                ->withSession(['current_account_id' => $account->id])
                ->get(route('audit-log.index'))
                ->assertForbidden();
        }
    }

    public function test_query_string_tampering_does_not_leak_other_accounts_rows(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Tamper Primary');
        $otherAccount = $this->createAccount('Tamper Other');

        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);

        AuditLog::create([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'auditable_type' => Transaction::class,
            'auditable_id' => 303,
            'event' => AuditLog::EVENT_CREATED,
            'changes' => ['quantity' => 4],
            'created_at' => now(),
        ]);

        AuditLog::create([
            'account_id' => $otherAccount->id,
            'user_id' => $user->id,
            'auditable_type' => Transaction::class,
            'auditable_id' => 404,
            'event' => AuditLog::EVENT_CREATED,
            'changes' => ['quantity' => 8],
            'created_at' => now()->subMinute(),
        ]);

        $response = $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('audit-log.index', ['account_id' => $otherAccount->id]));

        $auditEntries = $response->viewData('auditEntries');

        $response->assertOk()
            ->assertSeeText('#303');

        $this->assertSame([$account->id], $auditEntries->getCollection()->pluck('account_id')->unique()->values()->all());
        $this->assertSame([303], $auditEntries->getCollection()->pluck('auditable_id')->all());
    }

    public function test_event_and_entity_type_filters_narrow_results_and_survive_pagination(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Filtered Account');

        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);

        for ($index = 1; $index <= 30; $index++) {
            AuditLog::create([
                'account_id' => $account->id,
                'user_id' => $user->id,
                'auditable_type' => Service::class,
                'auditable_id' => $index,
                'event' => AuditLog::EVENT_UPDATED,
                'changes' => ['status' => ['old' => 'Awaiting Service', 'new' => 'Service Open']],
                'created_at' => now()->subMinutes($index),
            ]);
        }

        AuditLog::create([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'auditable_type' => Transaction::class,
            'auditable_id' => 999,
            'event' => AuditLog::EVENT_CREATED,
            'changes' => ['quantity' => 1],
            'created_at' => now()->subHours(2),
        ]);

        $response = $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('audit-log.index', [
                'event' => AuditLog::EVENT_UPDATED,
                'entity_type' => Service::class,
            ]));

        $auditEntries = $response->viewData('auditEntries');
        $expectedPageTwoUrl = route('audit-log.index').'?'.http_build_query([
            'event' => AuditLog::EVENT_UPDATED,
            'entity_type' => Service::class,
            'page' => 2,
        ]);

        $response->assertOk();

        $this->assertSame($expectedPageTwoUrl, $auditEntries->nextPageUrl());

        $this->assertCount(25, $auditEntries->getCollection());
        $this->assertSame([Service::class], $auditEntries->getCollection()->pluck('auditable_type')->unique()->values()->all());
        $this->assertSame([AuditLog::EVENT_UPDATED], $auditEntries->getCollection()->pluck('event')->unique()->values()->all());

        $pageTwoResponse = $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('audit-log.index', [
                'page' => 2,
                'event' => AuditLog::EVENT_UPDATED,
                'entity_type' => Service::class,
            ]));

        $pageTwoEntries = $pageTwoResponse->viewData('auditEntries');

        $pageTwoResponse->assertOk()
            ->assertSeeText('#26');

        $this->assertSame([26, 27, 28, 29, 30], $pageTwoEntries->getCollection()->pluck('auditable_id')->values()->all());
    }

    public function test_system_is_shown_for_entries_without_a_user(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('System Account');

        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);

        AuditLog::create([
            'account_id' => $account->id,
            'user_id' => null,
            'auditable_type' => Service::class,
            'auditable_id' => 777,
            'event' => AuditLog::EVENT_CREATED,
            'changes' => ['status' => 'Awaiting Service'],
            'created_at' => now(),
        ]);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('audit-log.index'))
            ->assertOk()
            ->assertSeeText('System');
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
