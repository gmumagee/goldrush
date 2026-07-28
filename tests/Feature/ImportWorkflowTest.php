<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\DataDictionary;
use App\Models\Location;
use App\Models\LocationContact;
use App\Models\Machine;
use App\Models\Product;
use App\Models\RouteLocation;
use App\Models\User;
use App\Models\VendingRoute;
use App\Models\Vendor;
use App\Services\ImportAuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_analyze_previews_create_and_update_without_writing_and_confirm_commits_with_batch_audit_and_ignores_account_id(): void
    {
        Storage::fake('private');

        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Import Product Account');
        $otherAccount = $this->createAccount('Import Product Foreign');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);

        $vendor = $this->createVendor($account, 'Preview Vendor');
        $existingProduct = $this->createProduct($account, [
            'vendor_id' => null,
            'sku' => 'SKU-UPDATE',
            'product_name' => 'Original Cola',
            'brand' => 'Old Brand',
        ]);

        $file = $this->createImportFile(
            ['sku', 'category', 'brand', 'product_name', 'size', 'package_type', 'barcode', 'vendor_name', 'account_id'],
            [
                ['SKU-UPDATE', 'Beverage', 'New Brand', 'Updated Cola', '20 oz', 'Bottle', '111', 'Preview Vendor', (string) $otherAccount->id],
                ['SKU-CREATE', 'Snack', 'Fresh Brand', 'New Chips', '', '', '', '', (string) $otherAccount->id],
            ]
        );

        $response = $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('import-export.import.analyze'), [
                'entity' => 'products',
                'import_file' => $file,
            ]);

        $response->assertOk();

        $preview = $response->viewData('importPreview');

        $this->assertSame([
            'create' => 1,
            'update' => 1,
            'error' => 0,
            'duplicate_warning' => 0,
        ], $preview['counts']);
        $this->assertSame('update', $preview['rows'][0]['action']);
        $this->assertSame('create', $preview['rows'][1]['action']);
        $this->assertSame('Old Brand', $existingProduct->fresh()->brand);
        $this->assertDatabaseMissing('tbl_products', [
            'account_id' => $account->id,
            'sku' => 'SKU-CREATE',
        ]);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('import-export.import.confirm'), [
                'entity' => 'products',
                'token' => $preview['token'],
            ])
            ->assertRedirect(route('import-export.index'))
            ->assertSessionHas('status', 'Imported: 1 created, 1 updated.');

        $this->assertDatabaseHas('tbl_products', [
            'id' => $existingProduct->id,
            'account_id' => $account->id,
            'vendor_id' => $vendor->id,
            'brand' => 'New Brand',
            'product_name' => 'Updated Cola',
        ]);
        $this->assertDatabaseHas('tbl_products', [
            'account_id' => $account->id,
            'sku' => 'SKU-CREATE',
            'product_name' => 'New Chips',
        ]);
        $this->assertDatabaseMissing('tbl_products', [
            'account_id' => $otherAccount->id,
            'sku' => 'SKU-CREATE',
        ]);

        $auditEntries = AuditLog::query()
            ->where('account_id', $account->id)
            ->where('user_id', $user->id)
            ->where('auditable_type', Product::class)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $auditEntries);
        $this->assertSame(['updated', 'created'], $auditEntries->pluck('event')->all());
        $this->assertCount(1, $auditEntries->pluck('batch_id')->unique()->filter()->all());
    }

    public function test_machine_rows_with_unresolvable_locations_preview_as_errors_and_are_skipped_on_commit(): void
    {
        Storage::fake('private');

        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Import Machine Account');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);
        $this->createDictionaryEntry(null, DataDictionary::GROUP_MACHINE_STATUS, Machine::STATUS_ACTIVE, Machine::STATUS_ACTIVE);

        $route = $this->createRoute($account, 'Machine Import Route');
        $location = $this->createCustomerLocation($account, $route, 'Known Machine Stop');

        $file = $this->createImportFile(
            ['serial_number', 'type', 'model', 'status', 'installed_on', 'location_name'],
            [
                ['SER-100', 'snack', 'Valid Machine', Machine::STATUS_ACTIVE, '2026-07-27', $location->location_name],
                ['SER-200', 'combo', 'Invalid Machine', Machine::STATUS_ACTIVE, '2026-07-27', 'Missing Stop'],
            ]
        );

        $response = $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('import-export.import.analyze'), [
                'entity' => 'machines',
                'import_file' => $file,
            ]);

        $response->assertOk();

        $preview = $response->viewData('importPreview');

        $this->assertSame([
            'create' => 1,
            'update' => 0,
            'error' => 1,
            'duplicate_warning' => 0,
        ], $preview['counts']);
        $this->assertSame('error', $preview['rows'][1]['action']);
        $this->assertStringContainsString("Location 'Missing Stop' not found.", $preview['rows'][1]['message']);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('import-export.import.confirm'), [
                'entity' => 'machines',
                'token' => $preview['token'],
            ])
            ->assertRedirect(route('import-export.index'))
            ->assertSessionHas('status', 'Imported: 1 created, 0 updated.');

        $this->assertDatabaseHas('tbl_machines', [
            'account_id' => $account->id,
            'serial_number' => 'SER-100',
            'location_id' => $location->id,
        ]);
        $this->assertDatabaseMissing('tbl_machines', [
            'account_id' => $account->id,
            'serial_number' => 'SER-200',
        ]);
    }

    public function test_locations_and_contacts_preview_as_create_with_duplicate_warnings_and_still_import_on_confirm(): void
    {
        Storage::fake('private');

        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Import Create Only Account');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);

        $route = $this->createRoute($account, 'Create Only Route');
        $existingLocation = $this->createCustomerLocation($account, $route, 'Duplicate Stop');
        $existingContact = $this->createContact($account, [
            'first_name' => 'Jamie',
            'last_name' => 'Jones',
            'email' => 'jamie@example.com',
        ]);

        $locationPreview = $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('import-export.import.analyze'), [
                'entity' => 'locations',
                'import_file' => $this->createImportFile(
                    ['location_name', 'address', 'city', 'state', 'zip_code', 'primary_route_name', 'primary_contact_name', 'primary_contact_email', 'primary_contact_phone'],
                    [
                        ['Duplicate Stop', '1 Main', 'Albany', 'NY', '12207', '', '', '', ''],
                        ['Fresh Stop', '2 Main', 'Albany', 'NY', '12207', '', '', '', ''],
                    ]
                ),
            ]);

        $locationPreview->assertOk();

        $locationPreviewData = $locationPreview->viewData('importPreview');

        $this->assertSame([
            'create' => 2,
            'update' => 0,
            'error' => 0,
            'duplicate_warning' => 1,
        ], $locationPreviewData['counts']);
        $this->assertSame('create', $locationPreviewData['rows'][0]['action']);
        $this->assertStringContainsString('Possible duplicate', $locationPreviewData['rows'][0]['message']);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('import-export.import.confirm'), [
                'entity' => 'locations',
                'token' => $locationPreviewData['token'],
            ])
            ->assertRedirect(route('import-export.index'));

        $this->assertSame(2, Location::query()->where('account_id', $account->id)->where('location_name', 'Duplicate Stop')->count());
        $this->assertDatabaseHas('tbl_locations', [
            'account_id' => $account->id,
            'location_name' => 'Fresh Stop',
        ]);

        $contactPreview = $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('import-export.import.analyze'), [
                'entity' => 'contacts',
                'import_file' => $this->createImportFile(
                    ['first_name', 'last_name', 'organization', 'title', 'email', 'phone', 'mobile_phone', 'location_name', 'contact_role', 'is_primary'],
                    [
                        ['Jamie', 'Jones', '', 'Manager', 'jamie@example.com', '', '', 'Fresh Stop', '', '0'],
                    ]
                ),
            ]);

        $contactPreview->assertOk();

        $contactPreviewData = $contactPreview->viewData('importPreview');

        $this->assertSame([
            'create' => 1,
            'update' => 0,
            'error' => 0,
            'duplicate_warning' => 1,
        ], $contactPreviewData['counts']);
        $this->assertSame('create', $contactPreviewData['rows'][0]['action']);
        $this->assertStringContainsString('Possible duplicate', $contactPreviewData['rows'][0]['message']);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('import-export.import.confirm'), [
                'entity' => 'contacts',
                'token' => $contactPreviewData['token'],
            ])
            ->assertRedirect(route('import-export.index'));

        $this->assertSame(2, Contact::query()->where('account_id', $account->id)->where('email', 'jamie@example.com')->count());
        $freshLocation = Location::query()
            ->where('account_id', $account->id)
            ->where('location_name', 'Fresh Stop')
            ->firstOrFail();

        $this->assertDatabaseHas('tbl_location_contacts', [
            'account_id' => $account->id,
            'location_id' => $freshLocation->id,
        ]);
        $this->assertTrue($existingContact->exists);
    }

    public function test_confirm_uses_the_exact_stashed_file_for_the_given_token(): void
    {
        Storage::fake('private');

        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Import Token Account');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);

        $firstPreview = $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('import-export.import.analyze'), [
                'entity' => 'products',
                'import_file' => $this->createImportFile(
                    ['sku', 'category', 'brand', 'product_name', 'size', 'package_type', 'barcode', 'vendor_name'],
                    [
                        ['TOK-100', 'Beverage', 'Batch One', 'First File Cola', '', '', '', ''],
                    ]
                ),
            ]);

        $firstPreview->assertOk();
        $token = $firstPreview->viewData('importPreview')['token'];

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('import-export.import.analyze'), [
                'entity' => 'products',
                'import_file' => $this->createImportFile(
                    ['sku', 'category', 'brand', 'product_name', 'size', 'package_type', 'barcode', 'vendor_name'],
                    [
                        ['TOK-200', 'Beverage', 'Batch Two', 'Second File Cola', '', '', '', ''],
                    ]
                ),
            ])
            ->assertOk();

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('import-export.import.confirm'), [
                'entity' => 'products',
                'token' => $token,
            ])
            ->assertRedirect(route('import-export.index'));

        $this->assertDatabaseHas('tbl_products', [
            'account_id' => $account->id,
            'sku' => 'TOK-100',
            'product_name' => 'First File Cola',
        ]);
        $this->assertDatabaseMissing('tbl_products', [
            'account_id' => $account->id,
            'sku' => 'TOK-200',
        ]);
    }

    public function test_invalid_or_expired_token_fails_cleanly(): void
    {
        Storage::fake('private');

        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Import Expired Token Account');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);

        $preview = $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('import-export.import.analyze'), [
                'entity' => 'products',
                'import_file' => $this->createImportFile(
                    ['sku', 'category', 'brand', 'product_name', 'size', 'package_type', 'barcode', 'vendor_name'],
                    [
                        ['EXP-100', 'Beverage', 'Brand', 'Expired Cola', '', '', '', ''],
                    ]
                ),
            ]);

        $preview->assertOk();
        $token = $preview->viewData('importPreview')['token'];
        Cache::forget('import-preview:'.$token);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->from(route('import-export.index'))
            ->post(route('import-export.import.confirm'), [
                'entity' => 'products',
                'token' => $token,
            ])
            ->assertRedirect(route('import-export.index'))
            ->assertSessionHasErrors('import_file');
    }

    public function test_import_commit_is_atomic_when_audit_logging_fails_mid_batch(): void
    {
        Storage::fake('private');

        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Import Rollback Account');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);

        $preview = $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('import-export.import.analyze'), [
                'entity' => 'products',
                'import_file' => $this->createImportFile(
                    ['sku', 'category', 'brand', 'product_name', 'size', 'package_type', 'barcode', 'vendor_name'],
                    [
                        ['RB-100', 'Beverage', 'Brand', 'Rollback Cola A', '', '', '', ''],
                        ['RB-200', 'Beverage', 'Brand', 'Rollback Cola B', '', '', '', ''],
                    ]
                ),
            ]);

        $preview->assertOk();
        $token = $preview->viewData('importPreview')['token'];

        $this->mock(ImportAuditLogger::class, function ($mock): void {
            $mock->shouldReceive('logCreated')
                ->twice()
                ->andReturnUsing(function () {
                    static $calls = 0;
                    $calls++;

                    if ($calls === 2) {
                        throw new \RuntimeException('Audit write failed.');
                    }
                });
        });

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('import-export.import.confirm'), [
                'entity' => 'products',
                'token' => $token,
            ])
            ->assertStatus(500);

        $this->assertDatabaseMissing('tbl_products', [
            'account_id' => $account->id,
            'sku' => 'RB-100',
        ]);
        $this->assertDatabaseMissing('tbl_products', [
            'account_id' => $account->id,
            'sku' => 'RB-200',
        ]);
        $this->assertDatabaseCount('tbl_audit_log', 0);
    }

    public function test_viewer_cannot_analyze_or_confirm_imports(): void
    {
        Storage::fake('private');

        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Import Viewer Account');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_VIEWER);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('import-export.import.analyze'), [
                'entity' => 'products',
                'import_file' => $this->createImportFile(
                    ['sku', 'category', 'brand', 'product_name', 'size', 'package_type', 'barcode', 'vendor_name'],
                    [['VIEW-100', 'Beverage', 'Brand', 'Viewer Cola', '', '', '', '']]
                ),
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('import-export.import.confirm'), [
                'entity' => 'products',
                'token' => 'bogus-token',
            ])
            ->assertForbidden();
    }

    public function test_header_mismatch_is_rejected_with_a_clear_error(): void
    {
        Storage::fake('private');

        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Import Header Account');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->from(route('import-export.index'))
            ->post(route('import-export.import.analyze'), [
                'entity' => 'products',
                'import_file' => $this->createImportFile(
                    ['sku', 'brand', 'product_name'],
                    [['BAD-100', 'Brand', 'Broken Header Cola']]
                ),
            ])
            ->assertRedirect(route('import-export.index'))
            ->assertSessionHasErrors('import_file');
    }

    protected function createImportFile(array $headers, array $rows): UploadedFile
    {
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, $headers);

        foreach ($rows as $row) {
            fputcsv($stream, $row);
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return UploadedFile::fake()->createWithContent('import.csv', $csv ?: '');
    }

    protected function createAccount(string $name): Account
    {
        return Account::create([
            'account_name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)).'-'.uniqid(),
            'status' => Account::STATUS_ACTIVE,
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

    protected function createVendor(Account $account, string $name): Vendor
    {
        return Vendor::create([
            'account_id' => $account->id,
            'vendor_name' => $name,
            'location' => $name.' Warehouse',
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

    protected function createCustomerLocation(Account $account, VendingRoute $route, string $name): Location
    {
        $location = Location::create([
            'account_id' => $account->id,
            'location_name' => $name,
            'address' => '123 Main Street',
            'city' => 'New York',
            'state' => 'NY',
            'zip_code' => '10001',
            'is_inventory' => null,
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

    protected function createProduct(Account $account, array $attributes): Product
    {
        return Product::create(array_merge([
            'account_id' => $account->id,
            'vendor_id' => null,
            'category' => 'Beverage',
            'brand' => 'Brand',
            'sku' => uniqid('sku-', true),
            'product_name' => 'Product',
            'size' => null,
            'package_type' => null,
            'barcode' => null,
        ], $attributes));
    }

    protected function createContact(Account $account, array $attributes): Contact
    {
        return Contact::create(array_merge([
            'account_id' => $account->id,
            'first_name' => 'First',
            'last_name' => 'Last',
            'organization' => null,
            'title' => null,
            'email' => null,
            'phone' => null,
            'mobile_phone' => null,
            'notes' => null,
        ], $attributes));
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
