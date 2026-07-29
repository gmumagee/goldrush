<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogArchiveService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class AuditLogArchiveCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_old_rows_are_exported_and_deleted_while_newer_rows_are_retained(): void
    {
        Storage::fake('private');
        Carbon::setTestNow('2026-07-29 10:00:00');

        $account = $this->createAccount('Archive Account');
        $user = User::factory()->create();

        $oldRow = AuditLog::query()->create([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'auditable_type' => Account::class,
            'auditable_id' => $account->id,
            'event' => AuditLog::EVENT_CREATED,
            'batch_id' => 'archive-old',
            'changes' => ['status' => 'created'],
            'created_at' => now()->subDays(31),
        ]);

        $newRow = AuditLog::query()->create([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'auditable_type' => Account::class,
            'auditable_id' => $account->id,
            'event' => AuditLog::EVENT_UPDATED,
            'batch_id' => 'archive-new',
            'changes' => ['status' => ['old' => 'created', 'new' => 'updated']],
            'created_at' => now()->subDays(5),
        ]);

        $this->artisan('audit-log:archive')
            ->expectsOutputToContain('Archived 1 audit log row(s)')
            ->assertExitCode(0);

        $archiveFiles = Storage::disk('private')->files('audit-archives');

        $this->assertCount(1, $archiveFiles);

        $csv = Storage::disk('private')->get($archiveFiles[0]);
        $lines = preg_split("/\r\n|\n|\r/", trim($csv)) ?: [];

        $this->assertCount(2, $lines);
        $this->assertSame(
            ['id', 'account_id', 'user_id', 'auditable_type', 'auditable_id', 'event', 'batch_id', 'changes', 'created_at'],
            str_getcsv($lines[0])
        );

        $dataRow = str_getcsv($lines[1]);

        $this->assertSame((string) $oldRow->id, $dataRow[0]);
        $this->assertSame('archive-old', $dataRow[6]);
        $this->assertStringContainsString('"status":"created"', $dataRow[7]);

        $this->assertDatabaseMissing('tbl_audit_log', ['id' => $oldRow->id]);
        $this->assertDatabaseHas('tbl_audit_log', ['id' => $newRow->id]);
    }

    public function test_command_is_a_clean_no_op_when_no_rows_are_older_than_the_cutoff(): void
    {
        Storage::fake('private');
        Carbon::setTestNow('2026-07-29 10:00:00');

        $account = $this->createAccount('No Op Account');

        AuditLog::query()->create([
            'account_id' => $account->id,
            'user_id' => null,
            'auditable_type' => Account::class,
            'auditable_id' => $account->id,
            'event' => AuditLog::EVENT_CREATED,
            'changes' => ['status' => 'created'],
            'created_at' => now()->subDays(2),
        ]);

        $this->artisan('audit-log:archive')
            ->expectsOutputToContain('No audit log rows older than')
            ->assertExitCode(0);

        Storage::disk('private')->assertDirectoryEmpty('audit-archives');
        $this->assertDatabaseCount('tbl_audit_log', 1);
    }

    public function test_rows_are_not_deleted_when_archive_generation_fails(): void
    {
        Carbon::setTestNow('2026-07-29 10:00:00');

        $account = $this->createAccount('Failure Account');

        $oldRow = AuditLog::query()->create([
            'account_id' => $account->id,
            'user_id' => null,
            'auditable_type' => Account::class,
            'auditable_id' => $account->id,
            'event' => AuditLog::EVENT_CREATED,
            'changes' => ['status' => 'created'],
            'created_at' => now()->subDays(31),
        ]);

        $service = Mockery::mock(AuditLogArchiveService::class);
        $service
            ->shouldReceive('archiveOlderThan')
            ->once()
            ->andThrow(new \RuntimeException('Simulated write failure.'));

        $this->app->instance(AuditLogArchiveService::class, $service);

        $this->artisan('audit-log:archive')
            ->expectsOutputToContain('Audit log archive failed: Simulated write failure.')
            ->assertExitCode(1);

        $this->assertDatabaseHas('tbl_audit_log', ['id' => $oldRow->id]);
    }

    public function test_schedule_list_includes_the_audit_log_archive_command(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('php artisan audit-log:archive');
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
}
