<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\DataDictionary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataDictionaryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_view_dictionary_management_page_with_global_and_account_values(): void
    {
        $user = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
            'is_super_admin' => true,
        ]);
        $account = $this->createAccount('Alpha Vending');
        $otherAccount = $this->createAccount('Beta Vending');

        $globalEntry = $this->createDictionaryEntry(null, DataDictionary::GROUP_LOCATION_CONTACT_ROLE, 'site_contact', 'Site Contact');
        $accountEntry = $this->createDictionaryEntry($account->id, DataDictionary::GROUP_LOCATION_CONTACT_ROLE, 'emergency_contact', 'Emergency Contact');
        $this->createDictionaryEntry($otherAccount->id, DataDictionary::GROUP_LOCATION_CONTACT_ROLE, 'private_role', 'Private Role');

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('data-dictionary.index'))
            ->assertOk()
            ->assertSeeText('Data Dictionary')
            ->assertSeeText('Name')
            ->assertSeeText($globalEntry->name)
            ->assertSeeText($globalEntry->value)
            ->assertSeeText($accountEntry->value)
            ->assertSeeText('View only')
            ->assertDontSeeText('Private Role');
    }

    public function test_super_admin_can_open_dictionary_management_page(): void
    {
        $user = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
            'is_super_admin' => true,
        ]);
        $account = $this->createAccount('Admin Account');

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('data-dictionary.index'))
            ->assertOk();
    }

    public function test_owner_admin_manager_technician_and_viewer_cannot_manage_dictionary_values(): void
    {
        $account = $this->createAccount('Restricted Dictionary Account');
        $entry = $this->createDictionaryEntry($account->id, DataDictionary::GROUP_LOCATION_CONTACT_ROLE, 'private_role', 'Private Role');

        foreach ([
            AccountUser::ROLE_OWNER,
            AccountUser::ROLE_ADMIN,
            AccountUser::ROLE_MANAGER,
            AccountUser::ROLE_TECHNICIAN,
            AccountUser::ROLE_VIEWER,
        ] as $role) {
            $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
            $this->attachUserToAccount($user, $account, $role);

            $session = ['current_account_id' => $account->id];

            $this->actingAs($user)->withSession($session)
                ->get(route('data-dictionary.index'))
                ->assertForbidden();

            $this->actingAs($user)->withSession($session)
                ->get(route('data-dictionary.create'))
                ->assertForbidden();

            $this->actingAs($user)->withSession($session)
                ->post(route('data-dictionary.store'), [
                    'name' => DataDictionary::GROUP_LOCATION_CONTACT_ROLE,
                    'value' => 'Emergency Contact',
                    'display_name' => 'Emergency Contact',
                ])
                ->assertForbidden();

            $this->actingAs($user)->withSession($session)
                ->get(route('data-dictionary.edit', $entry))
                ->assertForbidden();

            $this->actingAs($user)->withSession($session)
                ->put(route('data-dictionary.update', $entry), [
                    'value' => 'Updated Role',
                    'display_name' => 'Updated Role',
                ])
                ->assertForbidden();

            $this->actingAs($user)->withSession($session)
                ->post(route('data-dictionary.deactivate', $entry))
                ->assertForbidden();

            $this->actingAs($user)->withSession($session)
                ->post(route('data-dictionary.activate', $entry))
                ->assertForbidden();
        }
    }

    public function test_create_form_only_shows_name_value_and_display_name_inputs(): void
    {
        $user = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
            'is_super_admin' => true,
        ]);
        $account = $this->createAccount('Form Account');
        $this->createDictionaryEntry(null, DataDictionary::GROUP_LOCATION_CONTACT_ROLE, 'site_contact', 'Site Contact');

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('data-dictionary.create'))
            ->assertOk()
            ->assertSee('name="name"', false)
            ->assertSee('name="value"', false)
            ->assertSee('name="display_name"', false)
            ->assertDontSeeText('Dictionary Group')
            ->assertSeeText('Name')
            ->assertSeeText(DataDictionary::GROUP_LOCATION_CONTACT_ROLE);
    }

    public function test_super_admin_can_create_account_specific_dictionary_value_with_next_sort_order(): void
    {
        $user = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
            'is_super_admin' => true,
        ]);
        $account = $this->createAccount('Create Account');
        $this->createDictionaryEntry(null, DataDictionary::GROUP_LOCATION_CONTACT_ROLE, 'site_contact', 'Site Contact', 10);
        $this->createDictionaryEntry($account->id, DataDictionary::GROUP_LOCATION_CONTACT_ROLE, 'manager', 'Manager', 20);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('data-dictionary.store'), [
                'name' => DataDictionary::GROUP_LOCATION_CONTACT_ROLE,
                'value' => 'Emergency Contact',
                'display_name' => 'Emergency Contact',
            ])
            ->assertRedirect(route('data-dictionary.index', ['name' => DataDictionary::GROUP_LOCATION_CONTACT_ROLE]))
            ->assertSessionHas('status', 'Dictionary value added successfully.');

        $this->assertDatabaseHas('tbl_data_dictionary', [
            'account_id' => $account->id,
            'name' => DataDictionary::GROUP_LOCATION_CONTACT_ROLE,
            'value' => 'Emergency Contact',
            'label' => 'Emergency Contact',
            'sort_order' => 30,
            'is_active' => 1,
        ]);
    }

    public function test_duplicate_value_is_blocked_across_global_and_account_scope(): void
    {
        $user = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
            'is_super_admin' => true,
        ]);
        $account = $this->createAccount('Duplicate Account');
        $this->createDictionaryEntry(null, DataDictionary::GROUP_LOCATION_CONTACT_ROLE, 'regional_manager', 'Regional Manager');

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->from(route('data-dictionary.create'))
            ->post(route('data-dictionary.store'), [
                'name' => DataDictionary::GROUP_LOCATION_CONTACT_ROLE,
                'value' => 'Regional Manager',
                'display_name' => 'Regional Manager',
            ])
            ->assertRedirect(route('data-dictionary.create'))
            ->assertSessionHasErrors([
                'value' => 'This value already exists for the selected name.',
            ]);

        $this->assertSame(
            1,
            DataDictionary::query()
                ->forAccountScope($account->id)
                ->where('name', DataDictionary::GROUP_LOCATION_CONTACT_ROLE)
                ->where('value', 'Regional Manager')
                ->count()
        );
    }

    public function test_super_admin_can_edit_activate_and_deactivate_account_specific_dictionary_values(): void
    {
        $user = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
            'is_super_admin' => true,
        ]);
        $account = $this->createAccount('Lifecycle Account');
        $entry = $this->createDictionaryEntry($account->id, DataDictionary::GROUP_LOCATION_DOCUMENT_TYPE, 'photo_id', 'Photo ID');

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->put(route('data-dictionary.update', $entry), [
                'value' => 'Photo Identification',
                'display_name' => 'Photo Identification',
            ])
            ->assertRedirect(route('data-dictionary.index', ['name' => $entry->name]))
            ->assertSessionHas('status', 'Dictionary value updated successfully.');

        $entry->refresh();
        $this->assertSame('Photo Identification', $entry->value);
        $this->assertSame('Photo Identification', $entry->label);
        $this->assertSame(10, $entry->sort_order);
        $this->assertTrue($entry->is_active);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('data-dictionary.deactivate', $entry))
            ->assertRedirect(route('data-dictionary.index', ['name' => $entry->name]))
            ->assertSessionHas('status', 'Dictionary value deactivated successfully.');

        $entry->refresh();
        $this->assertFalse($entry->is_active);
        $this->assertDatabaseHas('tbl_data_dictionary', ['id' => $entry->id]);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('data-dictionary.activate', $entry))
            ->assertRedirect(route('data-dictionary.index', ['name' => $entry->name]))
            ->assertSessionHas('status', 'Dictionary value reactivated successfully.');

        $entry->refresh();
        $this->assertTrue($entry->is_active);
    }

    public function test_global_dictionary_values_cannot_be_edited_from_account_tool(): void
    {
        $user = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
            'is_super_admin' => true,
        ]);
        $account = $this->createAccount('Read Only Account');
        $globalEntry = $this->createDictionaryEntry(null, DataDictionary::GROUP_SERVICE_STATUS, 'service_completed', 'Service Completed');

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('data-dictionary.edit', $globalEntry))
            ->assertRedirect(route('data-dictionary.index'))
            ->assertSessionHasErrors([
                'data_dictionary' => 'Global dictionary values cannot be edited here.',
            ]);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('data-dictionary.deactivate', $globalEntry))
            ->assertRedirect(route('data-dictionary.index'))
            ->assertSessionHasErrors([
                'data_dictionary' => 'Global dictionary values cannot be edited here.',
            ]);

        $globalEntry->refresh();
        $this->assertTrue($globalEntry->is_active);
    }

    public function test_dictionary_values_cannot_be_managed_across_accounts(): void
    {
        $user = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
            'is_super_admin' => true,
        ]);
        $accountA = $this->createAccount('Account A');
        $accountB = $this->createAccount('Account B');
        $entry = $this->createDictionaryEntry($accountB->id, DataDictionary::GROUP_LOCATION_CONTACT_ROLE, 'private_role', 'Private Role');

        $this->actingAs($user)
            ->withSession(['current_account_id' => $accountA->id])
            ->get(route('data-dictionary.edit', $entry))
            ->assertNotFound();

        $this->actingAs($user)
            ->withSession(['current_account_id' => $accountA->id])
            ->put(route('data-dictionary.update', $entry), [
                'value' => 'Updated Role',
                'display_name' => 'Updated Role',
            ])
            ->assertNotFound();

        $this->actingAs($user)
            ->withSession(['current_account_id' => $accountA->id])
            ->post(route('data-dictionary.deactivate', $entry))
            ->assertNotFound();
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
            'status' => AccountUser::STATUS_ACTIVE,
        ]);
    }

    public function test_store_rejects_a_name_not_present_in_the_dropdown(): void
    {
        $user = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
            'is_super_admin' => true,
        ]);
        $account = $this->createAccount('Validation Account');
        $this->createDictionaryEntry(null, DataDictionary::GROUP_LOCATION_CONTACT_ROLE, 'site_contact', 'Site Contact');

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->from(route('data-dictionary.create'))
            ->post(route('data-dictionary.store'), [
                'name' => 'made_up_name',
                'value' => 'Regional Manager',
                'display_name' => 'Regional Manager',
            ])
            ->assertRedirect(route('data-dictionary.create'))
            ->assertSessionHasErrors('name');
    }

    protected function createDictionaryEntry(?int $accountId, string $group, string $dictionaryKey, string $value, int $sortOrder = 10): DataDictionary
    {
        return DataDictionary::create([
            'account_id' => $accountId,
            'name' => $group,
            'value' => $value,
            'label' => $value,
            'sort_order' => $sortOrder,
            'is_active' => true,
        ]);
    }
}
