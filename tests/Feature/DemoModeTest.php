<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\CalendarEvent;
use App\Models\Machine;
use App\Models\Plan;
use App\Models\Service;
use App\Models\ServiceSale;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Demo;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class DemoModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_route_is_unavailable_and_banner_is_absent_when_demo_mode_is_off(): void
    {
        $this->get('/demo')->assertNotFound();

        $account = Account::withoutEvents(fn () => Account::query()->create([
            'plan_id' => Plan::FREE_ID,
            'account_name' => 'Standard Account',
            'slug' => 'standard-account',
            'status' => Account::STATUS_ACTIVE,
            'billing_email' => 'owner@example.com',
        ]));

        $user = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);

        AccountUser::query()->create([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'role' => AccountUser::ROLE_OWNER,
            'status' => AccountUser::STATUS_ACTIVE,
        ]);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSeeText('DEMO ENVIRONMENT');
    }

    public function test_demo_route_auto_logs_in_shared_demo_user_and_renders_banner(): void
    {
        Config::set('demo.enabled', true);
        app(DemoSeeder::class)->run();

        $demoAccount = Account::query()->where('slug', Demo::accountSlug())->firstOrFail();

        $response = $this->get('/demo');

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
        $this->assertSame(Demo::sharedUserEmail(), auth()->user()?->email);
        $this->assertSame($demoAccount->id, session('current_account_id'));

        $this->followRedirects($response)
            ->assertOk()
            ->assertSeeText('DEMO ENVIRONMENT')
            ->assertSeeText('data resets nightly');
    }

    public function test_demo_mode_disables_sensitive_routes_and_upgrade_intents(): void
    {
        Config::set('demo.enabled', true);
        app(DemoSeeder::class)->run();

        $demoAccount = Account::query()->where('slug', Demo::accountSlug())->firstOrFail();
        $demoUser = User::query()->where('email', Demo::sharedUserEmail())->firstOrFail();
        $plan = Plan::query()->where('slug', Plan::PRO_SLUG)->firstOrFail();

        $this->actingAs($demoUser)
            ->withSession(['current_account_id' => $demoAccount->id])
            ->get(route('import-export.index'))
            ->assertNotFound();

        $superAdmin = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
            'is_super_admin' => true,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($superAdmin)
            ->get(route('admin.accounts.index'))
            ->assertNotFound();

        $this->actingAs($demoUser)
            ->withSession(['current_account_id' => $demoAccount->id])
            ->from(route('machines.index'))
            ->post(route('plan-upgrade-intents.store'), [
                'plan_id' => $plan->id,
                'source' => 'demo_test',
            ])
            ->assertRedirect(route('machines.index'))
            ->assertSessionHas('status', 'Upgrade requests are disabled in the public demo.');

        $this->assertDatabaseCount('plan_upgrade_requests', 0);
    }

    public function test_demo_seeder_builds_expected_shared_demo_baseline(): void
    {
        Config::set('demo.enabled', true);

        app(DemoSeeder::class)->run();

        $demoAccount = Account::query()
            ->with('plan')
            ->where('slug', Demo::accountSlug())
            ->firstOrFail();

        $this->assertSame(Plan::PRO_ID, (int) $demoAccount->plan_id);
        $this->assertSame(Demo::sharedUserEmail(), User::query()->where('email', Demo::sharedUserEmail())->value('email'));
        $this->assertGreaterThanOrEqual(5, $demoAccount->locations()->notInventory()->count());
        $this->assertGreaterThanOrEqual(2, $demoAccount->routes()->count());
        $this->assertGreaterThanOrEqual(28, $demoAccount->services()->count());
        $this->assertGreaterThanOrEqual(
            4,
            $demoAccount->services()->where('service_type', Service::TYPE_MAINTENANCE_SERVICE)->count()
        );
        $this->assertTrue(
            Machine::query()
                ->where('account_id', $demoAccount->id)
                ->where('serial_number', 'INV-COMBO-001')
                ->whereHas('location', fn ($query) => $query->inventory())
                ->exists()
        );
        $this->assertGreaterThanOrEqual(
            400,
            Transaction::query()->where('account_id', $demoAccount->id)->count()
        );
        $this->assertSame(
            $demoAccount->services()->count(),
            CalendarEvent::query()
                ->where('account_id', $demoAccount->id)
                ->where('source_type', CalendarEvent::SOURCE_TYPE_SERVICE)
                ->count()
        );
        $this->assertGreaterThanOrEqual(
            3,
            CalendarEvent::query()
                ->where('account_id', $demoAccount->id)
                ->where('source_type', CalendarEvent::SOURCE_TYPE_SERVICE)
                ->where('status', CalendarEvent::STATUS_SCHEDULED)
                ->count()
        );

        $calculatedSales = ServiceSale::query()
            ->where('account_id', $demoAccount->id)
            ->where('calculation_status', ServiceSale::CALCULATION_CALCULATED)
            ->orderBy('sales_date')
            ->get();

        $this->assertGreaterThanOrEqual(25, $calculatedSales->count());
        $this->assertNotNull($calculatedSales->first());
        $this->assertNotNull($calculatedSales->last());
        $this->assertGreaterThanOrEqual(
            28,
            $calculatedSales->first()->sales_date->diffInDays($calculatedSales->last()->sales_date)
        );
    }
}
