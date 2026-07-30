<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Location;
use App\Models\Machine;
use App\Models\User;
use App\Support\Demo;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DemoResetCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_reset_refuses_to_run_when_demo_mode_is_off(): void
    {
        $this->artisan('demo:reset')
            ->expectsOutput('Demo reset refused because APP_DEMO_MODE is disabled.')
            ->assertExitCode(1);
    }

    public function test_demo_reset_restores_demo_baseline(): void
    {
        Config::set('demo.enabled', true);
        app(DemoSeeder::class)->run();

        $demoAccount = Account::query()->where('slug', Demo::accountSlug())->firstOrFail();

        Machine::query()
            ->where('account_id', $demoAccount->id)
            ->where('serial_number', 'INV-COMBO-001')
            ->delete();

        Location::query()->create([
            'account_id' => $demoAccount->id,
            'location_name' => 'Temporary Demo Site',
            'address' => '1 Demo Way',
            'city' => 'Toronto',
            'state' => 'ON',
            'zip_code' => 'M5V1A1',
        ]);

        $createdUser = User::query()->create([
            'name' => 'Visitor Created User',
            'email' => 'visitor-created@example.com',
            'password' => bcrypt('Password-123!'),
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);

        DB::table('tbl_account_users')->insert([
            'account_id' => $demoAccount->id,
            'user_id' => $createdUser->id,
            'role' => 'Viewer',
            'status' => 'active',
        ]);

        $this->artisan('demo:reset')
            ->expectsOutput('Demo tenant reset to the seeded baseline.')
            ->assertExitCode(0);

        $resetAccount = Account::query()->where('slug', Demo::accountSlug())->firstOrFail();

        $this->assertTrue(
            Machine::query()
                ->where('account_id', $resetAccount->id)
                ->where('serial_number', 'INV-COMBO-001')
                ->exists()
        );
        $this->assertDatabaseMissing('tbl_locations', [
            'account_id' => $resetAccount->id,
            'location_name' => 'Temporary Demo Site',
        ]);
        $this->assertDatabaseMissing('tbl_users', [
            'email' => 'visitor-created@example.com',
        ]);
    }
}
