<?php

namespace App\Console\Commands;

use App\Services\ProductCatalogImporter;
use Illuminate\Console\Command;

class ValidateProductCatalog extends Command
{
    protected $signature = 'products:validate-catalog
        {--path= : Path to the CSV, absolute or relative to the project base. Defaults to config(products.default_catalog_path)}';

    protected $description = 'Validate a product catalog CSV without writing to the database.';

    public function handle(ProductCatalogImporter $importer): int
    {
        $path = $this->resolveCsvPath($this->option('path'));
        $catalog = $importer->inspectCatalog($path);

        $this->line('Catalog path: '.$path);
        $this->line('Total data rows: '.$catalog['total_rows']);
        $this->line('Valid rows: '.$catalog['valid_rows']);
        $this->line('Skipped rows: '.$catalog['skipped']);

        if ($catalog['errors'] === []) {
            $this->info('Catalog is valid.');

            return self::SUCCESS;
        }

        $this->warn('Catalog validation found issues:');

        foreach ($catalog['errors'] as $error) {
            $this->line(' - '.$this->formatCatalogError($error));
        }

        return self::FAILURE;
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
