<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\Location;
use App\Models\Machine;
use App\Models\RouteLocation;
use App\Models\User;
use App\Models\VendingRoute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MachineIndexGroupingTest extends TestCase
{
    use RefreshDatabase;

    public function test_machine_index_groups_paginated_results_by_visible_machine_type_and_keeps_uncategorized_last(): void
    {
        // Seed raw stored type values so the index proves it groups directly from tbl_machines.type and only sends blank types to Uncategorized.
        $user = User::factory()->create(['status' => 'active']);
        $account = $this->createAccount('Grouped Machines');
        $otherAccount = $this->createAccount('Other Grouped Machines');
        $this->attachUserToAccount($user, $account, 'owner');

        $location = $this->createLocation($account, $this->createRoute($account, 'North Route'), 'Campus Center');
        $otherLocation = $this->createLocation($otherAccount, $this->createRoute($otherAccount, 'South Route'), 'Remote Stop');

        $beverageMachine = $this->createMachine($account, $location, 'Beverage Machine', 'B-100', 'Alpha Beverage');
        $snackMachineA = $this->createMachine($account, $location, 'Snack Machine', 'S-200', 'Bravo Snack');
        $snackMachineB = $this->createMachine($account, $location, 'Snack Machine', 'S-100', 'Alpha Snack');
        $legacyMachine = $this->createMachine($account, $location, 'legacy_type', 'U-100', 'Alpha Legacy');
        $blankTypeMachine = $this->createMachine($account, $location, '', 'U-200', 'Zulu Unknown');
        $whitespaceTypeMachine = $this->createMachine($account, $location, '   ', 'U-300', 'Whitespace Unknown');
        $this->createMachine($otherAccount, $otherLocation, 'snack', 'FOREIGN-100', 'Foreign Snack');

        DB::enableQueryLog();

        $response = $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('machines.index'));

        $response->assertOk()
            ->assertSeeText('Machines')
            ->assertSeeText('Beverage Machine')
            ->assertSeeText('Snack Machine')
            ->assertSeeText('legacy_type')
            ->assertSeeText('Uncategorized')
            ->assertSeeTextInOrder(['Beverage Machine', 'legacy_type', 'Snack Machine', 'Uncategorized'])
            ->assertSee('id="machine-type-group-0-heading"', false)
            ->assertSee('id="machine-type-group-0-collapse"', false)
            ->assertSee('id="machine-type-group-1-heading"', false)
            ->assertSee('id="machine-type-group-1-collapse"', false)
            ->assertSee('id="machine-type-group-2-heading"', false)
            ->assertSee('id="machine-type-group-2-collapse"', false)
            ->assertSee('id="machine-type-group-3-heading"', false)
            ->assertSee('id="machine-type-group-3-collapse"', false)
            ->assertSee('x-data="{ open: false }"', false)
            ->assertSee(':aria-expanded="open.toString()"', false)
            ->assertSee('x-show="open"', false)
            ->assertDontSee('data-bs-parent', false)
            ->assertSeeText('Serial Number')
            ->assertSeeText('Model')
            ->assertSeeText('Location')
            ->assertSeeText('Status')
            ->assertSeeText('Installed On')
            ->assertSeeText('Actions')
            ->assertDontSee('>Type<', false)
            ->assertSeeText('View')
            ->assertSeeText('Edit')
            ->assertSeeText('Add Bin')
            ->assertSeeText('Delete')
            ->assertSee('href="'.route('machines.show', $beverageMachine).'"', false)
            ->assertSee('href="'.route('machines.edit', $beverageMachine).'"', false)
            ->assertSee('href="'.route('bins.create', ['machine_id' => $beverageMachine->id]).'"', false)
            ->assertDontSeeText('Foreign Snack')
            ->assertViewHas('machineGroups', function (Collection $machineGroups) use (
                $beverageMachine,
                $snackMachineA,
                $snackMachineB,
                $legacyMachine,
                $blankTypeMachine,
                $whitespaceTypeMachine
            ) {
                $labels = $machineGroups->pluck('label')->all();

                if ($labels !== ['Beverage Machine', 'legacy_type', 'Snack Machine', 'Uncategorized']) {
                    return false;
                }

                    return $machineGroups->pluck('count')->all() === [1, 1, 2, 2]
                        && $machineGroups[0]['machines']->pluck('id')->all() === [$beverageMachine->id]
                        && $machineGroups[1]['machines']->pluck('id')->all() === [$legacyMachine->id]
                        && $machineGroups[2]['machines']->pluck('id')->all() === [$snackMachineB->id, $snackMachineA->id]
                        && $machineGroups[3]['machines']->pluck('id')->all() === [$whitespaceTypeMachine->id, $blankTypeMachine->id]
                        && $machineGroups[3]['is_uncategorized'] === true;
            });

        $locationQueries = collect(DB::getQueryLog())
            ->filter(function (array $query) {
                $sql = strtolower($query['query']);

                return str_contains($sql, 'from "tbl_locations"')
                    || str_contains($sql, 'from `tbl_locations`');
            });

        $this->assertCount(1, $locationQueries);
    }

    public function test_machine_index_applies_search_before_grouping_and_preserves_query_strings_in_pagination_links(): void
    {
        // Keep the existing search-first pagination flow intact while grouping only the current filtered page.
        $user = User::factory()->create(['status' => 'active']);
        $account = $this->createAccount('Filtered Machines');
        $this->attachUserToAccount($user, $account, 'owner');
        $location = $this->createLocation($account, $this->createRoute($account, 'East Route'), 'Research Park');

        foreach (range(1, 26) as $index) {
            $this->createMachine(
                $account,
                $location,
                'snack',
                'MATCH-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                'Snack Model '.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
            );
        }

        $this->createMachine($account, $location, 'combo', 'OTHER-01', 'Combo Model 01');

        $response = $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('machines.index', ['search' => 'MATCH']));

        $response->assertOk()
            ->assertSeeText('snack')
            ->assertDontSeeText('combo')
            ->assertSee('href="http://localhost/machines?search=MATCH&amp;page=2"', false)
            ->assertViewHas('machineGroups', function (Collection $machineGroups) {
                return $machineGroups->count() === 1
                    && $machineGroups->first()['label'] === 'snack'
                    && $machineGroups->first()['count'] === 25;
            });
    }

    public function test_machine_index_shows_empty_state_when_no_filtered_machines_match(): void
    {
        // Keep the list readable by showing the empty state instead of rendering an empty accordion shell.
        $user = User::factory()->create(['status' => 'active']);
        $account = $this->createAccount('Empty Machine Search');
        $this->attachUserToAccount($user, $account, 'owner');
        $location = $this->createLocation($account, $this->createRoute($account, 'West Route'), 'Downtown');

        $this->createMachine($account, $location, 'snack', 'SNACK-01', 'Snack Model');

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('machines.index', ['search' => 'NO-MATCH']))
            ->assertOk()
            ->assertSeeText('No machines found for this account.')
            ->assertDontSee('machine-type-group-0-heading', false);
    }

    public function test_machine_index_renders_statuses_as_required_pill_badges_without_mutating_stored_values(): void
    {
        // Render status pills from the stored value at display time so active and inactive machines get the required colors while unknown values stay visible.
        $user = User::factory()->create(['status' => 'active']);
        $account = $this->createAccount('Machine Status Badges');
        $otherAccount = $this->createAccount('Other Machine Status Badges');
        $this->attachUserToAccount($user, $account, 'owner');

        $location = $this->createLocation($account, $this->createRoute($account, 'Badge Route'), 'Badge Stop');
        $otherLocation = $this->createLocation($otherAccount, $this->createRoute($otherAccount, 'Other Badge Route'), 'Other Badge Stop');

        $activeMachine = $this->createMachine($account, $location, 'Snack Machine', 'ACTIVE-01', 'Active Model', 'Active');
        $inactiveMachine = $this->createMachine($account, $location, 'Snack Machine', 'INACTIVE-01', 'Inactive Model', ' inactive ');
        $unknownMachine = $this->createMachine($account, $location, 'Snack Machine', 'UNKNOWN-01', 'Unknown Model', 'paused');
        $blankStatusMachine = $this->createMachine($account, $location, 'Snack Machine', 'BLANK-01', 'Blank Model', '   ');
        $this->createMachine($otherAccount, $otherLocation, 'Snack Machine', 'FOREIGN-01', 'Foreign Model', 'inactive');

        $response = $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('machines.index'));

        $response->assertOk()
            ->assertSee('bg-blue-100 px-2.5 py-1 text-xs font-medium text-blue-800 dark:bg-blue-500/15 dark:text-blue-300', false)
            ->assertSee('bg-green-100 px-2.5 py-1 text-xs font-medium text-green-800 dark:bg-green-500/15 dark:text-green-300', false)
            ->assertSee('bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700 dark:bg-gray-700/60 dark:text-gray-200', false)
            ->assertSeeText('Active')
            ->assertSeeText('Inactive')
            ->assertSeeText('paused')
            ->assertSeeText('Unknown')
            ->assertDontSeeText('FOREIGN-01');

        $this->assertSame('Active', $activeMachine->fresh()->status);
        $this->assertSame(' inactive ', $inactiveMachine->fresh()->status);
        $this->assertSame('paused', $unknownMachine->fresh()->status);
        $this->assertSame('   ', $blankStatusMachine->fresh()->status);
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

    protected function attachUserToAccount(User $user, Account $account, string $role): void
    {
        AccountUser::create([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => 'active',
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
            'address' => '123 Machine Lane',
            'city' => 'Toronto',
            'state' => 'ON',
            'zip_code' => 'M1M1M1',
        ]);

        RouteLocation::create([
            'account_id' => $account->id,
            'route_id' => $route->id,
            'location_id' => $location->id,
            'stop_order' => (int) RouteLocation::query()
                ->where('account_id', $account->id)
                ->where('route_id', $route->id)
                ->max('stop_order') + 1,
            'is_primary' => true,
        ]);

        return $location;
    }

    protected function createMachine(
        Account $account,
        Location $location,
        string $type,
        string $serialNumber,
        string $model,
        string $status = 'active',
    ): Machine {
        return Machine::create([
            'account_id' => $account->id,
            'location_id' => $location->id,
            'type' => $type,
            'serial_number' => $serialNumber,
            'model' => $model,
            'status' => $status,
            'installed_on' => '2026-07-01',
        ]);
    }
}
