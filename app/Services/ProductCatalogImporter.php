<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Product;
use Throwable;

class ProductCatalogImporter
{
    /**
     * @var array<string, array{
     *     path:string,
     *     headers:array<int, string>,
     *     total_rows:int,
     *     valid_rows:int,
     *     skipped:int,
     *     rows:array<int, array{
     *         row_number:int,
     *         category:string,
     *         brand:string,
     *         product_name:string,
     *         size:string,
     *         package_type:string,
     *         suggested_sku:string
     *     }>,
     *     errors:array<int, array{row:int|null,fields:array<int,string>,message:string}>
     * }>
     */
    protected array $catalogCache = [];

    /**
     * @var array<int, string>
     */
    protected const EXPECTED_HEADERS = [
        'category',
        'brand',
        'product_name',
        'size',
        'package_type',
        'suggested_sku',
    ];

    /**
     * @var array<int, string>
     */
    protected const REQUIRED_FIELDS = [
        'product_name',
        'suggested_sku',
    ];

    /**
     * @return array{
     *     created:int,
     *     updated:int,
     *     skipped:int,
     *     errors:array<int, array{row:int|null,fields:array<int,string>,message:string}>,
     *     total_rows:int,
     *     valid_rows:int
     * }
     */
    public function importForAccount(Account $account, string $csvPath): array
    {
        $catalog = $this->inspectCatalog($csvPath);
        $summary = [
            'created' => 0,
            'updated' => 0,
            'skipped' => $catalog['skipped'],
            'errors' => $catalog['errors'],
            'total_rows' => $catalog['total_rows'],
            'valid_rows' => $catalog['valid_rows'],
        ];

        foreach ($catalog['rows'] as $row) {
            try {
                $product = Product::updateOrCreate(
                    [
                        'account_id' => $account->id,
                        'sku' => $row['suggested_sku'],
                    ],
                    [
                        'category' => $row['category'],
                        'brand' => $row['brand'],
                        'product_name' => $row['product_name'],
                        'size' => $row['size'],
                        'package_type' => $row['package_type'],
                    ]
                );
            } catch (Throwable $exception) {
                $summary['skipped']++;
                $summary['errors'][] = [
                    'row' => $row['row_number'],
                    'fields' => [],
                    'message' => 'Database write failed: '.$exception->getMessage(),
                ];

                continue;
            }

            $product->wasRecentlyCreated ? $summary['created']++ : $summary['updated']++;
        }

        return $summary;
    }

    /**
     * @return array{
     *     path:string,
     *     headers:array<int, string>,
     *     total_rows:int,
     *     valid_rows:int,
     *     skipped:int,
     *     rows:array<int, array{
     *         row_number:int,
     *         category:string,
     *         brand:string,
     *         product_name:string,
     *         size:string,
     *         package_type:string,
     *         suggested_sku:string
     *     }>,
     *     errors:array<int, array{row:int|null,fields:array<int,string>,message:string}>
     * }
     */
    public function inspectCatalog(string $csvPath): array
    {
        if (isset($this->catalogCache[$csvPath])) {
            return $this->catalogCache[$csvPath];
        }

        $catalog = [
            'path' => $csvPath,
            'headers' => [],
            'total_rows' => 0,
            'valid_rows' => 0,
            'skipped' => 0,
            'rows' => [],
            'errors' => [],
        ];

        if ($csvPath === '' || ! is_readable($csvPath)) {
            $catalog['errors'][] = [
                'row' => null,
                'fields' => [],
                'message' => 'Cannot read CSV at: '.$csvPath,
            ];

            return $this->catalogCache[$csvPath] = $catalog;
        }

        $handle = fopen($csvPath, 'r');

        if ($handle === false) {
            $catalog['errors'][] = [
                'row' => null,
                'fields' => [],
                'message' => 'Failed to open CSV for reading.',
            ];

            return $this->catalogCache[$csvPath] = $catalog;
        }

        try {
            $header = fgetcsv($handle);

            if ($header === false) {
                $catalog['errors'][] = [
                    'row' => null,
                    'fields' => [],
                    'message' => 'CSV file is empty.',
                ];

                return $this->catalogCache[$csvPath] = $catalog;
            }

            $catalog['headers'] = array_map(
                static fn ($column) => trim((string) $column, " \xEF\xBB\xBF"),
                $header
            );

            $missingHeaders = array_values(array_diff(self::EXPECTED_HEADERS, $catalog['headers']));

            if ($missingHeaders !== []) {
                $catalog['errors'][] = [
                    'row' => null,
                    'fields' => $missingHeaders,
                    'message' => 'Missing required header columns.',
                ];

                return $this->catalogCache[$csvPath] = $catalog;
            }

            $rowNumber = 1;

            while (($data = fgetcsv($handle)) !== false) {
                $rowNumber++;
                $catalog['total_rows']++;

                if ($this->rowIsBlank($data)) {
                    $catalog['skipped']++;

                    continue;
                }

                if (count($data) !== count($catalog['headers'])) {
                    $catalog['skipped']++;
                    $catalog['errors'][] = [
                        'row' => $rowNumber,
                        'fields' => [],
                        'message' => sprintf(
                            'Column count mismatch. Expected %d columns, found %d.',
                            count($catalog['headers']),
                            count($data)
                        ),
                    ];

                    continue;
                }

                $row = array_map(
                    static fn ($value) => trim((string) $value),
                    array_combine($catalog['headers'], $data)
                );

                $missingFields = $this->missingRequiredFields($row);

                if ($missingFields !== []) {
                    $catalog['skipped']++;
                    $catalog['errors'][] = [
                        'row' => $rowNumber,
                        'fields' => $missingFields,
                        'message' => 'Missing required field values.',
                    ];

                    continue;
                }

                $catalog['rows'][] = [
                    'row_number' => $rowNumber,
                    'category' => $row['category'] ?? '',
                    'brand' => $row['brand'] ?? '',
                    'product_name' => $row['product_name'] ?? '',
                    'size' => $row['size'] ?? '',
                    'package_type' => $row['package_type'] ?? '',
                    'suggested_sku' => $row['suggested_sku'] ?? '',
                ];
                $catalog['valid_rows']++;
            }
        } finally {
            fclose($handle);
        }

        return $this->catalogCache[$csvPath] = $catalog;
    }

    /**
     * @param  array<int, string|null>  $data
     */
    protected function rowIsBlank(array $data): bool
    {
        foreach ($data as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, string>  $row
     * @return array<int, string>
     */
    protected function missingRequiredFields(array $row): array
    {
        $missingFields = [];

        foreach (self::REQUIRED_FIELDS as $field) {
            if (($row[$field] ?? '') === '') {
                $missingFields[] = $field;
            }
        }

        return $missingFields;
    }
}
