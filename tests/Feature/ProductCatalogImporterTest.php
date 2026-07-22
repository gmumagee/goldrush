<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Product;
use App\Services\ProductCatalogImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCatalogImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_importing_the_same_csv_twice_for_the_same_account_is_idempotent(): void
    {
        $account = $this->createAccountWithoutObserver('Importer Account');
        $importer = $this->app->make(ProductCatalogImporter::class);
        $path = $this->writeCatalogCsv(<<<'CSV'
category,brand,product_name,size,package_type,suggested_sku
Soda,Coca-Cola,Coca-Cola,12 oz,Can,1
Candy,M&M's,M&M's Plain,1.69 oz,Bag,2
CSV);

        $firstImport = $importer->importForAccount($account, $path);
        $secondImport = $importer->importForAccount($account, $path);

        $this->assertSame(2, $firstImport['created']);
        $this->assertSame(0, $firstImport['updated']);
        $this->assertSame(0, $firstImport['skipped']);
        $this->assertSame(0, $secondImport['created']);
        $this->assertSame(2, $secondImport['updated']);
        $this->assertSame(2, Product::query()->where('account_id', $account->id)->count());
    }

    public function test_malformed_rows_are_skipped_without_crashing_the_import(): void
    {
        $account = $this->createAccountWithoutObserver('Malformed Account');
        $importer = $this->app->make(ProductCatalogImporter::class);
        $path = $this->writeCatalogCsv(<<<'CSV'
category,brand,product_name,size,package_type,suggested_sku
Soda,Coca-Cola,Coca-Cola,12 oz,Can,1
Soda,Coca-Cola,,20 oz,Bottle,2
CSV);

        $summary = $importer->importForAccount($account, $path);

        $this->assertSame(1, $summary['created']);
        $this->assertSame(0, $summary['updated']);
        $this->assertSame(1, $summary['skipped']);
        $this->assertCount(1, $summary['errors']);
        $this->assertSame(3, $summary['errors'][0]['row']);
        $this->assertSame(['product_name'], $summary['errors'][0]['fields']);
        $this->assertSame(1, Product::query()->where('account_id', $account->id)->count());
    }

    public function test_validate_catalog_reports_bad_rows_without_touching_the_database(): void
    {
        $path = $this->writeCatalogCsv(<<<'CSV'
category,brand,product_name,size,package_type,suggested_sku
Soda,Coca-Cola,Coca-Cola,12 oz,Can,1
Soda,Coca-Cola,,20 oz,Bottle,2
CSV);

        $this->artisan('products:validate-catalog', ['--path' => $path])
            ->expectsOutput('Catalog path: '.$path)
            ->expectsOutput('Total data rows: 2')
            ->expectsOutput('Valid rows: 1')
            ->expectsOutput('Skipped rows: 1')
            ->expectsOutput('Catalog validation found issues:')
            ->expectsOutput(' - Row 3: Missing required field values. [product_name]')
            ->assertExitCode(1);

        $this->assertDatabaseCount('tbl_products', 0);
    }

    protected function createAccountWithoutObserver(string $name): Account
    {
        return Account::withoutEvents(fn () => Account::create([
            'account_name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)).'-'.uniqid(),
            'status' => Account::STATUS_ACTIVE,
            'billing_email' => strtolower(str_replace(' ', '.', $name)).'@example.com',
        ]));
    }

    protected function writeCatalogCsv(string $contents): string
    {
        $path = storage_path('framework/testing/catalog-'.uniqid('', true).'.csv');
        file_put_contents($path, $contents);

        return $path;
    }
}
