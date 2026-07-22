<?php

namespace Tests\Feature;

use App\Jobs\ImportDefaultCatalogForAccount;
use App\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AccountDefaultCatalogImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_creation_dispatches_default_catalog_import_after_commit(): void
    {
        Queue::fake();

        $account = Account::create([
            'account_name' => 'Queued Account',
            'slug' => 'queued-account',
            'status' => Account::STATUS_ACTIVE,
            'billing_email' => 'queued@example.com',
        ]);

        Queue::assertPushed(ImportDefaultCatalogForAccount::class, function (ImportDefaultCatalogForAccount $job) use ($account): bool {
            return $job->accountId === $account->id && $job->afterCommit === true;
        });
    }
}
