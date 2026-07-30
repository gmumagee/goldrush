<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class MarketingPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_home_redirects_to_login_when_self_registration_is_disabled(): void
    {
        Config::set('tenancy.mode', Tenancy::MODE_MULTI);
        Config::set('security.allow_self_registration', false);

        $this->get(route('home'))
            ->assertRedirect(route('login'));
    }

    public function test_guest_home_redirects_to_login_when_self_registration_is_enabled_in_multi_tenant_mode(): void
    {
        Config::set('tenancy.mode', Tenancy::MODE_MULTI);
        Config::set('security.allow_self_registration', true);

        $this->get(route('home'))
            ->assertRedirect(route('login'));
    }

    public function test_removed_public_marketing_pages_return_404_for_guests_and_authenticated_users(): void
    {
        Config::set('tenancy.mode', Tenancy::MODE_MULTI);

        foreach (['/features', '/pricing', '/about'] as $url) {
            $this->get($url)->assertNotFound();
        }

        $user = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
        ]);

        foreach (['/features', '/pricing', '/about'] as $url) {
            $this->actingAs($user)->get($url)->assertNotFound();
        }
    }

    public function test_authenticated_verified_user_is_redirected_from_home_into_the_app(): void
    {
        Config::set('tenancy.mode', Tenancy::MODE_MULTI);

        $user = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
        ]);
        $account = $this->createAccount('Marketing Redirect Account');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('home'))
            ->assertRedirect(route('dashboard'));

        $this->app['session']->flush();

        $userWithoutSelection = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
        ]);
        $this->attachUserToAccount($userWithoutSelection, $account, AccountUser::ROLE_OWNER);

        $this->actingAs($userWithoutSelection)
            ->get(route('home'))
            ->assertRedirect(route('accounts.select'));
    }

    public function test_authenticated_unverified_user_is_redirected_from_home_to_verification_notice(): void
    {
        Config::set('tenancy.mode', Tenancy::MODE_MULTI);
        Config::set('security.require_verified_email', true);

        $user = User::factory()->unverified()->create([
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->actingAs($user)
            ->get(route('home'))
            ->assertRedirect(route('verification.notice'));
    }

    protected function createAccount(string $name): Account
    {
        return Account::withoutEvents(fn () => Account::query()->create([
            'account_name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)).'-'.uniqid(),
            'status' => Account::STATUS_ACTIVE,
            'billing_email' => strtolower(str_replace(' ', '.', $name)).'@example.com',
        ]));
    }

    protected function attachUserToAccount(User $user, Account $account, string $role): void
    {
        AccountUser::query()->create([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => AccountUser::STATUS_ACTIVE,
        ]);
    }
}
