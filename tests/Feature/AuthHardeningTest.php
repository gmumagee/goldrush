<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\DataDictionarySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class AuthHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_multi_tenant_self_registration_is_disabled_by_default(): void
    {
        Config::set('tenancy.mode', Tenancy::MODE_MULTI);
        Config::set('security.allow_self_registration', false);

        $this->get(route('register'))
            ->assertRedirect(route('login'));

        $this->post(route('register'), [
            'name' => 'Blocked User',
            'email' => 'blocked@example.com',
            'password' => 'Password-123!',
            'password_confirmation' => 'Password-123!',
            'account_name' => 'Blocked Account',
        ])->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount('tbl_accounts', 0);
        $this->assertDatabaseCount('tbl_users', 0);
    }

    public function test_unverified_user_is_redirected_to_email_verification_notice(): void
    {
        $account = $this->createAccount('Verification Account');
        $user = User::factory()->unverified()->create([
            'status' => User::STATUS_ACTIVE,
        ]);
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('dashboard'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_login_is_throttled_after_repeated_failed_attempts(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route('login'), [
                'email' => 'missing@example.com',
                'password' => 'wrong-password',
            ])->assertRedirect();
        }

        $this->post(route('login'), [
            'email' => 'missing@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }

    public function test_registration_is_throttled_when_self_registration_is_enabled(): void
    {
        Config::set('tenancy.mode', Tenancy::MODE_MULTI);
        Config::set('security.allow_self_registration', true);

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->post(route('register'), [
                'name' => '',
                'email' => '',
                'password' => '',
                'password_confirmation' => '',
                'account_name' => '',
            ])->assertRedirect();
        }

        $this->post(route('register'), [
            'name' => '',
            'email' => '',
            'password' => '',
            'password_confirmation' => '',
            'account_name' => '',
        ])->assertStatus(429);
    }

    public function test_account_user_creation_rejects_existing_global_email(): void
    {
        $this->seed(DataDictionarySeeder::class);

        $manager = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
        ]);
        $existingUser = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
            'email' => 'shared@example.com',
        ]);
        $managerAccount = $this->createAccount('Manager Account');
        $existingAccount = $this->createAccount('Existing Account');

        $this->attachUserToAccount($manager, $managerAccount, AccountUser::ROLE_OWNER);
        $this->attachUserToAccount($existingUser, $existingAccount, AccountUser::ROLE_OWNER);

        $this->actingAs($manager)
            ->withSession(['current_account_id' => $managerAccount->id])
            ->from(route('account-users.create'))
            ->post(route('account-users.store'), [
                'name' => 'Shared User',
                'email' => 'shared@example.com',
                'role' => AccountUser::ROLE_VIEWER,
                'status' => AccountUser::STATUS_ACTIVE,
                'user_status' => User::STATUS_ACTIVE,
                'password' => 'Password-123!',
                'password_confirmation' => 'Password-123!',
            ])
            ->assertRedirect(route('account-users.create'))
            ->assertSessionHasErrors('email');

        $this->assertDatabaseMissing('tbl_account_users', [
            'account_id' => $managerAccount->id,
            'user_id' => $existingUser->id,
        ]);
    }

    public function test_import_analysis_rejects_non_csv_uploads(): void
    {
        $account = $this->createAccount('Import Account');
        $owner = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
        ]);
        $this->attachUserToAccount($owner, $account, AccountUser::ROLE_OWNER);

        $this->actingAs($owner)
            ->withSession(['current_account_id' => $account->id])
            ->from(route('import-export.index'))
            ->post(route('import-export.import.analyze'), [
                'entity' => 'products',
                'import_file' => UploadedFile::fake()->image('not-a-csv.jpg'),
            ])
            ->assertRedirect(route('import-export.index'))
            ->assertSessionHasErrors('import_file');
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
