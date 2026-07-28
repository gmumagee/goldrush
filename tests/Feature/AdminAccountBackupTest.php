<?php

namespace Tests\Feature;

use App\Jobs\GenerateAccountBackup;
use App\Models\Account;
use App\Models\AccountBackup;
use App\Models\AccountUser;
use App\Models\AuditLog;
use App\Models\Bin;
use App\Models\Contact;
use App\Models\InventoryLedger;
use App\Models\Location;
use App\Models\LocationContact;
use App\Models\Machine;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\RouteLocation;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\User;
use App\Models\VendingRoute;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Services\AccountBackupArchiveService;
use App\Services\AccountExportService;
use App\Support\Tenancy;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;
use ZipArchive;

class AdminAccountBackupTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_super_admin_can_trigger_backup_and_job_produces_expected_bundle_zip(): void
    {
        Storage::fake('private');
        Queue::fake();
        Carbon::setTestNow('2026-07-28 10:15:00');
        CarbonImmutable::setTestNow('2026-07-28 10:15:00');

        $superAdmin = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
            'is_super_admin' => true,
        ]);

        $targetAccount = $this->createAccount('Target Backup Account');
        $foreignAccount = $this->createAccount('Foreign Backup Account');

        $this->seedBackupData($targetAccount, 'TARGET');
        $this->seedBackupData($foreignAccount, 'FOREIGN');

        $this->actingAs($superAdmin)
            ->withSession(['auth.password_confirmed_at' => now()->unix()])
            ->post(route('admin.accounts.backups.store', $targetAccount))
            ->assertRedirect(route('admin.accounts.index'))
            ->assertSessionHas('status', 'Account backup queued successfully.');

        Queue::assertPushed(GenerateAccountBackup::class);

        $backup = AccountBackup::query()->where('account_id', $targetAccount->id)->latest('id')->firstOrFail();
        $this->assertSame(AccountBackup::STATUS_PENDING, $backup->status);

        $this->assertDatabaseHas('tbl_audit_log', [
            'account_id' => $targetAccount->id,
            'user_id' => $superAdmin->id,
            'auditable_type' => AccountBackup::class,
            'auditable_id' => $backup->id,
            'event' => AuditLog::EVENT_CREATED,
        ]);

        (new GenerateAccountBackup($backup->id))->handle(app(AccountBackupArchiveService::class));

        $backup = $backup->fresh();
        $this->assertSame(AccountBackup::STATUS_READY, $backup->status);
        $this->assertNotNull($backup->file_path);
        Storage::disk('private')->assertExists($backup->file_path);

        $entries = $this->zipEntries(Storage::disk('private')->path($backup->file_path));

        $expectedFiles = [
            'products.csv',
            'machines.csv',
            'locations.csv',
            'contacts.csv',
            'services.csv',
            'transactions.csv',
            'purchases.csv',
            'purchase_items.csv',
            'inventory_ledger.csv',
            'manifest.txt',
        ];

        foreach ($expectedFiles as $expectedFile) {
            $this->assertArrayHasKey($expectedFile, $entries);
        }

        $this->assertArrayNotHasKey('users.csv', $entries);
        $this->assertArrayNotHasKey('account_users.csv', $entries);

        foreach ($entries as $filename => $content) {
            if ($filename === 'manifest.txt') {
                continue;
            }

            $this->assertStringNotContainsString('FOREIGN', $content, $filename.' leaked foreign account data.');
        }

        $this->assertStringContainsString('TARGET', $entries['products.csv']);
        $this->assertStringContainsString('TARGET', $entries['services.csv']);
        $this->assertStringContainsString('TARGET', $entries['inventory_ledger.csv']);
        $this->assertStringContainsString($targetAccount->account_name, $entries['manifest.txt']);
        $this->assertStringContainsString('products.csv', $entries['manifest.txt']);
        $this->assertStringContainsString('inventory_ledger.csv', $entries['manifest.txt']);
    }

    public function test_export_service_matches_session_scoped_export_csvs_for_import_export_entities(): void
    {
        Carbon::setTestNow('2026-07-28 10:15:00');
        CarbonImmutable::setTestNow('2026-07-28 10:15:00');

        $superAdmin = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
            'is_super_admin' => true,
        ]);

        $account = $this->createAccount('Parity Account');
        $this->seedBackupData($account, 'PARITY');

        $service = app(AccountExportService::class);

        foreach (['products', 'machines', 'locations', 'contacts'] as $entity) {
            $response = $this->actingAs($superAdmin)
                ->withSession(['current_account_id' => $account->id])
                ->get(route('import-export.export', ['entity' => $entity]));

            $response->assertOk();

            $this->assertSame(
                $service->csvContent($account, $entity),
                $response->streamedContent(),
                sprintf('Export mismatch for entity [%s].', $entity)
            );
        }
    }

    public function test_backup_status_transitions_to_failed_when_generation_errors_and_no_final_zip_is_left_behind(): void
    {
        Storage::fake('private');

        $account = $this->createAccount('Failure Account');
        $superAdmin = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
            'is_super_admin' => true,
        ]);

        $backup = AccountBackup::query()->create([
            'account_id' => $account->id,
            'requested_by_user_id' => $superAdmin->id,
            'status' => AccountBackup::STATUS_PENDING,
        ]);

        $archiveService = Mockery::mock(AccountBackupArchiveService::class);
        $archiveService
            ->shouldReceive('generate')
            ->once()
            ->andThrow(new \RuntimeException('Backup generation exploded.'));

        try {
            (new GenerateAccountBackup($backup->id))->handle($archiveService);
            $this->fail('Expected the backup job to throw.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Backup generation exploded.', $exception->getMessage());
        }

        $backup = $backup->fresh();
        $this->assertSame(AccountBackup::STATUS_FAILED, $backup->status);
        $this->assertNull($backup->file_path);
        $this->assertSame('Backup generation exploded.', $backup->failure_message);
        $this->assertSame([], Storage::disk('private')->allFiles((string) config('backups.directory', 'account-backups')));
    }

    public function test_non_super_admin_gets_forbidden_on_backup_trigger_and_download(): void
    {
        Storage::fake('private');

        $owner = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
            'is_super_admin' => false,
        ]);

        $account = $this->createAccount('Owned Account');
        $this->attachUserToAccount($owner, $account, AccountUser::ROLE_OWNER);

        Storage::disk('private')->put('account-backups/owned-account.zip', 'zip-content');

        $backup = AccountBackup::query()->create([
            'account_id' => $account->id,
            'requested_by_user_id' => $owner->id,
            'status' => AccountBackup::STATUS_READY,
            'file_disk' => 'private',
            'file_path' => 'account-backups/owned-account.zip',
            'file_name' => 'owned-account.zip',
            'file_size_bytes' => strlen('zip-content'),
        ]);

        $this->actingAs($owner)
            ->post(route('admin.accounts.backups.store', $account))
            ->assertForbidden();

        $this->actingAs($owner)
            ->get(route('admin.account-backups.download', $backup))
            ->assertForbidden();
    }

    public function test_pending_backup_blocks_duplicate_generation_requests(): void
    {
        Queue::fake();

        $superAdmin = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
            'is_super_admin' => true,
        ]);
        $account = $this->createAccount('Pending Guard Account');

        AccountBackup::query()->create([
            'account_id' => $account->id,
            'requested_by_user_id' => $superAdmin->id,
            'status' => AccountBackup::STATUS_PENDING,
        ]);

        $this->actingAs($superAdmin)
            ->withSession(['auth.password_confirmed_at' => now()->unix()])
            ->from(route('admin.accounts.index'))
            ->post(route('admin.accounts.backups.store', $account))
            ->assertRedirect(route('admin.accounts.index'))
            ->assertSessionHasErrors('backup');

        Queue::assertNotPushed(GenerateAccountBackup::class);
        $this->assertSame(1, AccountBackup::query()->where('account_id', $account->id)->count());
    }

    public function test_backup_routes_require_password_confirmation(): void
    {
        Storage::fake('private');

        $superAdmin = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
            'is_super_admin' => true,
        ]);
        $account = $this->createAccount('Password Confirm Account');

        Storage::disk('private')->put('account-backups/confirm-account.zip', 'zip-content');

        $backup = AccountBackup::query()->create([
            'account_id' => $account->id,
            'requested_by_user_id' => $superAdmin->id,
            'status' => AccountBackup::STATUS_READY,
            'file_disk' => 'private',
            'file_path' => 'account-backups/confirm-account.zip',
            'file_name' => 'confirm-account.zip',
            'file_size_bytes' => strlen('zip-content'),
        ]);

        $this->actingAs($superAdmin)
            ->post(route('admin.accounts.backups.store', $account))
            ->assertRedirect(route('password.confirm'));

        $this->actingAs($superAdmin)
            ->get(route('admin.account-backups.download', $backup))
            ->assertRedirect(route('password.confirm'));
    }

    public function test_backup_download_streams_the_zip_and_is_logged_to_the_audit_trail(): void
    {
        Storage::fake('private');

        $superAdmin = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
            'is_super_admin' => true,
        ]);
        $account = $this->createAccount('Download Account');

        Storage::disk('private')->put('account-backups/download-account.zip', 'zip-content');

        $backup = AccountBackup::query()->create([
            'account_id' => $account->id,
            'requested_by_user_id' => $superAdmin->id,
            'status' => AccountBackup::STATUS_READY,
            'file_disk' => 'private',
            'file_path' => 'account-backups/download-account.zip',
            'file_name' => 'download-account.zip',
            'file_size_bytes' => strlen('zip-content'),
        ]);

        $this->actingAs($superAdmin)
            ->withSession(['auth.password_confirmed_at' => now()->unix()])
            ->get(route('admin.account-backups.download', $backup))
            ->assertOk()
            ->assertDownload('download-account.zip');

        $this->assertDatabaseHas('tbl_audit_log', [
            'account_id' => $account->id,
            'user_id' => $superAdmin->id,
            'auditable_type' => AccountBackup::class,
            'auditable_id' => $backup->id,
            'event' => AuditLog::EVENT_UPDATED,
        ]);
    }

    public function test_retention_cleanup_removes_old_backups_and_keeps_newer_ones(): void
    {
        Storage::fake('private');
        Config::set('backups.retention_days', 90);
        Carbon::setTestNow('2026-07-28 10:15:00');
        CarbonImmutable::setTestNow('2026-07-28 10:15:00');

        $superAdmin = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
            'is_super_admin' => true,
        ]);
        $account = $this->createAccount('Retention Account');

        Storage::disk('private')->put('account-backups/old-backup.zip', 'old');
        Storage::disk('private')->put('account-backups/new-backup.zip', 'new');

        $oldBackup = AccountBackup::query()->create([
            'account_id' => $account->id,
            'requested_by_user_id' => $superAdmin->id,
            'status' => AccountBackup::STATUS_READY,
            'file_disk' => 'private',
            'file_path' => 'account-backups/old-backup.zip',
            'file_name' => 'old-backup.zip',
            'file_size_bytes' => 3,
        ]);
        $oldBackup->forceFill([
            'created_at' => now()->subDays(91),
            'updated_at' => now()->subDays(91),
        ])->saveQuietly();

        $newBackup = AccountBackup::query()->create([
            'account_id' => $account->id,
            'requested_by_user_id' => $superAdmin->id,
            'status' => AccountBackup::STATUS_READY,
            'file_disk' => 'private',
            'file_path' => 'account-backups/new-backup.zip',
            'file_name' => 'new-backup.zip',
            'file_size_bytes' => 3,
        ]);
        $newBackup->forceFill([
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ])->saveQuietly();

        $this->artisan('backups:prune-account-bundles')
            ->expectsOutput('Deleted 1 expired account backup(s).')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('tbl_account_backups', ['id' => $oldBackup->id]);
        $this->assertDatabaseHas('tbl_account_backups', ['id' => $newBackup->id]);
        Storage::disk('private')->assertMissing('account-backups/old-backup.zip');
        Storage::disk('private')->assertExists('account-backups/new-backup.zip');
    }

    public function test_account_backup_routes_are_unavailable_in_single_tenant_mode(): void
    {
        Config::set('tenancy.mode', Tenancy::MODE_SINGLE);
        Config::set('tenancy.single_tenant_account_id', 1);

        $superAdmin = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
            'is_super_admin' => true,
        ]);
        $account = $this->createAccount('Single Backup Account', 1);

        $this->actingAs($superAdmin)
            ->withSession(['auth.password_confirmed_at' => now()->unix()])
            ->post(route('admin.accounts.backups.store', $account))
            ->assertNotFound();
    }

    protected function zipEntries(string $absolutePath): array
    {
        $zip = new ZipArchive();
        $opened = $zip->open($absolutePath);
        $this->assertSame(true, $opened);

        $entries = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);
            $entries[$name] = (string) $zip->getFromIndex($index);
        }

        $zip->close();

        return $entries;
    }

    protected function seedBackupData(Account $account, string $prefix): void
    {
        $operator = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
            'name' => $prefix.' Operator',
            'email' => strtolower($prefix).'.operator@example.com',
        ]);
        $this->attachUserToAccount($operator, $account, AccountUser::ROLE_OWNER);

        $warehouse = Warehouse::create([
            'account_id' => $account->id,
            'warehouse_name' => $prefix.' Warehouse',
            'address' => '100 '.$prefix.' Way',
            'city' => 'Albany',
            'state' => 'NY',
            'zip_code' => '12207',
        ]);

        $vendor = Vendor::create([
            'account_id' => $account->id,
            'vendor_name' => $prefix.' Vendor',
            'location' => $prefix.' Vendor Dock',
        ]);

        $route = VendingRoute::create([
            'account_id' => $account->id,
            'route_name' => $prefix.' Route',
            'description' => $prefix.' Route Description',
        ]);

        $location = Location::create([
            'account_id' => $account->id,
            'location_name' => $prefix.' Stop',
            'address' => '200 '.$prefix.' Plaza',
            'city' => 'Albany',
            'state' => 'NY',
            'zip_code' => '12207',
            'is_inventory' => null,
        ]);

        RouteLocation::create([
            'account_id' => $account->id,
            'route_id' => $route->id,
            'location_id' => $location->id,
            'stop_order' => 1,
            'is_primary' => true,
        ]);

        $contact = Contact::create([
            'account_id' => $account->id,
            'first_name' => $prefix,
            'last_name' => 'Contact',
            'organization' => $prefix.' Organization',
            'title' => 'Manager',
            'email' => strtolower($prefix).'.contact@example.com',
            'phone' => '555-1000',
            'mobile_phone' => '555-2000',
            'notes' => $prefix.' Contact Note',
        ]);

        LocationContact::create([
            'account_id' => $account->id,
            'location_id' => $location->id,
            'contact_id' => $contact->id,
            'contact_role' => $prefix.' Role',
            'is_primary' => true,
            'notes' => $prefix.' Relationship',
        ]);

        $product = Product::create([
            'account_id' => $account->id,
            'vendor_id' => $vendor->id,
            'category' => $prefix.' Category',
            'brand' => $prefix.' Brand',
            'sku' => $prefix.'-SKU',
            'product_name' => $prefix.' Product',
            'size' => '12 oz',
            'package_type' => 'Can',
            'barcode' => $prefix.'-BARCODE',
        ]);

        $machine = Machine::create([
            'account_id' => $account->id,
            'location_id' => $location->id,
            'type' => 'snack',
            'serial_number' => $prefix.'-SERIAL',
            'model' => $prefix.' Model',
            'status' => Machine::STATUS_ACTIVE,
            'installed_on' => '2026-07-01',
        ]);

        $bin = Bin::create([
            'account_id' => $account->id,
            'machine_id' => $machine->id,
            'product_id' => $product->id,
            'bin_code' => $prefix.'-BIN',
            'capacity' => 12,
            'price' => 1.50,
        ]);

        $purchase = Purchase::create([
            'account_id' => $account->id,
            'vendor_id' => $vendor->id,
            'warehouse_id' => $warehouse->id,
            'invoice_number' => $prefix.'-INV',
            'purchase_date' => '2026-07-05',
            'status' => Purchase::STATUS_POSTED,
            'notes' => $prefix.' Purchase Notes',
        ]);

        PurchaseItem::create([
            'account_id' => $account->id,
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'quantity' => 24,
            'line_total' => 18.00,
            'unit_cost' => 0.75,
        ]);

        $service = Service::create([
            'account_id' => $account->id,
            'location_id' => $location->id,
            'warehouse_id' => $warehouse->id,
            'user_id' => $operator->id,
            'created_by_user_id' => $operator->id,
            'closed_by_user_id' => $operator->id,
            'service_type' => Service::TYPE_LOCATION,
            'notes' => $prefix.' Service Notes',
            'service_date' => '2026-07-10',
            'scheduled_at' => '2026-07-10 09:00:00',
            'opened_at' => '2026-07-10 09:15:00',
            'completed_at' => '2026-07-10 09:45:00',
            'closed_at' => '2026-07-10 10:00:00',
            'amount_collected' => 25.50,
            'status' => Service::STATUS_COMPLETED,
        ]);

        Transaction::create([
            'account_id' => $account->id,
            'service_id' => $service->id,
            'machine_id' => $machine->id,
            'bin_id' => $bin->id,
            'product_id' => $product->id,
            'transaction_type' => Transaction::TYPE_FILL,
            'quantity' => 10,
            'spoilage' => 1,
            'transaction_at' => '2026-07-10 09:30:00',
            'price' => 1.50,
            'unit_cost' => 0.75,
        ]);

        InventoryLedger::create([
            'account_id' => $account->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'movement_type' => InventoryLedger::MOVEMENT_TYPE_PURCHASE,
            'quantity_delta' => 24,
            'unit_cost' => 0.75,
            'total_cost' => 18.00,
            'source_type' => Purchase::class,
            'source_id' => $purchase->id,
            'movement_at' => '2026-07-05 08:00:00',
            'notes' => $prefix.' Ledger Notes',
        ]);
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
