<?php

namespace App\Jobs;

use App\Models\Account;
use App\Services\ProductCatalogImporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ImportDefaultCatalogForAccount implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public int $accountId)
    {
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300, 900, 1800];
    }

    public function handle(ProductCatalogImporter $importer): void
    {
        $account = Account::query()->find($this->accountId);

        if (! $account) {
            Log::warning('Default product catalog import skipped because the account no longer exists.', [
                'account_id' => $this->accountId,
            ]);

            return;
        }

        $path = (string) config('products.default_catalog_path');
        $summary = $importer->importForAccount($account, $path);

        if ($summary['errors'] === []) {
            return;
        }

        Log::warning('Default product catalog import completed with catalog errors.', [
            'account_id' => $account->id,
            'csv_path' => $path,
            'created' => $summary['created'],
            'updated' => $summary['updated'],
            'skipped' => $summary['skipped'],
            'errors' => $summary['errors'],
        ]);
    }
}
