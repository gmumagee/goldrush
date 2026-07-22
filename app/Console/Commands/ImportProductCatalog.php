<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Services\ProductCatalogImporter;
use Illuminate\Console\Command;

class ImportProductCatalog extends Command
{
    /**
     * php artisan products:import-catalog
     * php artisan products:import-catalog --path=storage/app/private/catalogs/default_products.csv
     * php artisan products:import-catalog --account_id=3
     * php artisan products:import-catalog --dry-run
     */
    protected $signature = 'products:import-catalog
        {--path= : Path to the CSV, absolute or relative to the project base. Defaults to config(products.default_catalog_path)}
        {--account_id= : Limit the import to a single account ID (defaults to all accounts)}
        {--dry-run : Parse and report without writing to the database}';

    protected $description = 'Import a product catalog CSV into tbl_products for one or all accounts';

    public function handle(ProductCatalogImporter $importer): int
    {
        $path = $this->resolveCsvPath($this->option('path'));
        $catalog = $importer->inspectCatalog($path);

        $accountId = $this->option('account_id');
        $accounts = $accountId
            ? Account::where('id', $accountId)->get()
            : Account::all();

        if ($accounts->isEmpty()) {
            $this->error($accountId ? "No account found with id={$accountId}" : 'No accounts exist yet.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        $this->info(sprintf(
            '%s %d products across %d account(s)...',
            $dryRun ? 'Dry run: would import' : 'Importing',
            $catalog['valid_rows'],
            $accounts->count()
        ));

        $this->line('Catalog path: '.$path);
        $this->line(sprintf(
            'Catalog rows: %d total, %d valid, %d skipped',
            $catalog['total_rows'],
            $catalog['valid_rows'],
            $catalog['skipped']
        ));

        foreach ($catalog['errors'] as $error) {
            $this->warn($this->formatCatalogError($error));
        }

        if ($catalog['valid_rows'] === 0) {
            $this->error('CSV contained no importable data rows.');

            return self::FAILURE;
        }

        foreach ($accounts as $account) {
            $summary = $dryRun
                ? ['created' => 0, 'updated' => 0]
                : $importer->importForAccount($account, $path);

            $this->line(sprintf(
                '  Account #%d (%s): %d rows%s',
                $account->id,
                $account->account_name,
                $catalog['valid_rows'],
                $dryRun ? '' : " -> created {$summary['created']}, updated {$summary['updated']}"
            ));
        }

        $this->info('Done.');

        return empty($catalog['errors']) ? self::SUCCESS : self::FAILURE;
    }

    private function resolveCsvPath(?string $pathOption): string
    {
        $path = $pathOption ?: config('products.default_catalog_path');

        if (! is_string($path) || $path === '') {
            return '';
        }

        return str_starts_with($path, DIRECTORY_SEPARATOR) ? $path : base_path($path);
    }

    /**
     * @param  array{row:int|null,fields:array<int,string>,message:string}  $error
     */
    private function formatCatalogError(array $error): string
    {
        $prefix = $error['row'] === null ? 'Catalog error' : 'Row '.$error['row'];

        if ($error['fields'] === []) {
            return $prefix.': '.$error['message'];
        }

        return $prefix.': '.$error['message'].' ['.implode(', ', $error['fields']).']';
    }
}
