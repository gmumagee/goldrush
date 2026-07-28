<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Bin;
use App\Models\Contact;
use App\Models\InventoryLedger;
use App\Models\Location;
use App\Models\Machine;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Service;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountExportService
{
    /**
     * @return array<string, array{label:string, model:class-string}>
     */
    public function importExportEntityDefinitions(): array
    {
        return [
            'products' => [
                'label' => 'Products',
                'model' => Product::class,
            ],
            'machines' => [
                'label' => 'Machines',
                'model' => Machine::class,
            ],
            'locations' => [
                'label' => 'Locations',
                'model' => Location::class,
            ],
            'contacts' => [
                'label' => 'Contacts',
                'model' => Contact::class,
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public function backupEntityKeys(): array
    {
        return [
            'products',
            'machines',
            'locations',
            'contacts',
            'services',
            'transactions',
            'purchases',
            'purchase_items',
            'inventory_ledger',
        ];
    }

    public function streamEntity(Account $account, string $entity, array $filters = []): StreamedResponse
    {
        $headers = $this->headers($entity);
        $filename = $this->downloadFilename($account, $entity);

        return response()->streamDownload(function () use ($account, $entity, $filters, $headers): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                throw new \RuntimeException('Unable to open CSV output stream.');
            }

            $this->writeCsv($handle, $headers, $this->rows($account, $entity, $filters));

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function csvContent(Account $account, string $entity, array $filters = []): string
    {
        $handle = fopen('php://temp', 'w+');

        if ($handle === false) {
            throw new \RuntimeException('Unable to open temporary CSV buffer.');
        }

        $this->writeCsv($handle, $this->headers($entity), $this->rows($account, $entity, $filters));
        rewind($handle);

        $content = stream_get_contents($handle);
        fclose($handle);

        return is_string($content) ? $content : '';
    }

    public function writeEntityCsvToPath(Account $account, string $entity, string $absolutePath, array $filters = []): int
    {
        $directory = dirname($absolutePath);

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new \RuntimeException(sprintf('Unable to create export directory [%s].', $directory));
        }

        $handle = fopen($absolutePath, 'wb');

        if ($handle === false) {
            throw new \RuntimeException(sprintf('Unable to open export file [%s].', $absolutePath));
        }

        try {
            return $this->writeCsv($handle, $this->headers($entity), $this->rows($account, $entity, $filters));
        } finally {
            fclose($handle);
        }
    }

    public function backupEntryFilename(string $entity): string
    {
        return $entity.'.csv';
    }

    public function downloadFilename(Account $account, string $entity, ?CarbonImmutable $timestamp = null): string
    {
        $timestamp ??= CarbonImmutable::now((string) config('app.timezone', 'UTC'));

        return sprintf(
            '%s-%s-%s.csv',
            $entity,
            Str::slug($account->slug ?: $account->account_name),
            $timestamp->toDateString(),
        );
    }

    /**
     * @return list<string>
     */
    protected function headers(string $entity): array
    {
        return match ($entity) {
            'products' => [
                'sku',
                'category',
                'brand',
                'product_name',
                'size',
                'package_type',
                'barcode',
                'vendor_name',
            ],
            'machines' => [
                'serial_number',
                'type',
                'model',
                'status',
                'installed_on',
                'location_name',
            ],
            'locations' => [
                'location_name',
                'address',
                'city',
                'state',
                'zip_code',
                'primary_route_name',
                'primary_contact_name',
                'primary_contact_email',
                'primary_contact_phone',
            ],
            'contacts' => [
                'first_name',
                'last_name',
                'organization',
                'title',
                'email',
                'phone',
                'mobile_phone',
                'location_name',
                'contact_role',
                'is_primary',
            ],
            'services' => [
                'id',
                'service_type',
                'status',
                'service_date',
                'scheduled_at',
                'opened_at',
                'completed_at',
                'closed_at',
                'amount_collected',
                'location_id',
                'location_name',
                'warehouse_id',
                'warehouse_name',
                'assigned_user_id',
                'assigned_user_name',
                'created_by_user_id',
                'created_by_user_name',
                'closed_by_user_id',
                'closed_by_user_name',
                'notes',
            ],
            'transactions' => [
                'id',
                'service_id',
                'machine_id',
                'machine_serial_number',
                'machine_model',
                'location_id',
                'location_name',
                'bin_id',
                'bin_code',
                'product_id',
                'product_name',
                'sku',
                'transaction_type',
                'quantity',
                'spoilage',
                'transaction_at',
                'price',
                'unit_cost',
            ],
            'purchases' => [
                'id',
                'purchase_date',
                'status',
                'invoice_number',
                'vendor_id',
                'vendor_name',
                'warehouse_id',
                'warehouse_name',
                'notes',
                'created_at',
            ],
            'purchase_items' => [
                'id',
                'purchase_id',
                'invoice_number',
                'purchase_date',
                'vendor_id',
                'vendor_name',
                'warehouse_id',
                'warehouse_name',
                'product_id',
                'product_name',
                'sku',
                'quantity',
                'unit_cost',
                'line_total',
                'created_at',
            ],
            'inventory_ledger' => [
                'id',
                'movement_at',
                'movement_type',
                'warehouse_id',
                'warehouse_name',
                'product_id',
                'product_name',
                'sku',
                'quantity_delta',
                'unit_cost',
                'total_cost',
                'source_type',
                'source_id',
                'notes',
                'created_at',
            ],
            default => throw new \InvalidArgumentException(sprintf('Unsupported export entity [%s].', $entity)),
        };
    }

    /**
     * @return iterable<int, array<int, mixed>>
     */
    protected function rows(Account $account, string $entity, array $filters = []): iterable
    {
        return match ($entity) {
            'products' => $this->productRows($account, $filters),
            'machines' => $this->machineRows($account, $filters),
            'locations' => $this->locationRows($account, $filters),
            'contacts' => $this->contactRows($account, $filters),
            'services' => $this->serviceRows($account),
            'transactions' => $this->transactionRows($account),
            'purchases' => $this->purchaseRows($account),
            'purchase_items' => $this->purchaseItemRows($account),
            'inventory_ledger' => $this->inventoryLedgerRows($account),
            default => throw new \InvalidArgumentException(sprintf('Unsupported export entity [%s].', $entity)),
        };
    }

    /**
     * @return iterable<int, array<int, mixed>>
     */
    protected function productRows(Account $account, array $filters = []): iterable
    {
        $search = trim((string) ($filters['search'] ?? ''));

        $products = Product::query()
            ->where('account_id', $account->id)
            ->with('vendor')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($productQuery) use ($search) {
                    $productQuery
                        ->where('sku', 'like', '%'.$search.'%')
                        ->orWhere('product_name', 'like', '%'.$search.'%')
                        ->orWhere('brand', 'like', '%'.$search.'%')
                        ->orWhere('category', 'like', '%'.$search.'%')
                        ->orWhere('barcode', 'like', '%'.$search.'%');
                });
            })
            ->orderByRaw("LOWER(TRIM(COALESCE(category, '')))")
            ->orderedForDropdown()
            ->orderBy('id');

        foreach ($products->lazy(200) as $product) {
            yield [
                $product->sku,
                $product->category,
                $product->brand,
                $product->product_name,
                $product->size,
                $product->package_type,
                $product->barcode,
                $product->vendor?->vendor_name,
            ];
        }
    }

    /**
     * @return iterable<int, array<int, mixed>>
     */
    protected function machineRows(Account $account, array $filters = []): iterable
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $locationScope = trim((string) ($filters['location_scope'] ?? ''));

        $machines = Machine::query()
            ->where('account_id', $account->id)
            ->with([
                'location' => fn ($query) => $query->where('account_id', $account->id),
            ])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($machineQuery) use ($search) {
                    $machineQuery
                        ->where('serial_number', 'like', '%'.$search.'%')
                        ->orWhere('model', 'like', '%'.$search.'%')
                        ->orWhere('status', 'like', '%'.$search.'%');
                });
            })
            ->when($locationScope === 'in_inventory', function ($query) {
                $query->whereHas('location', fn ($locationQuery) => $locationQuery->inventory());
            })
            ->when($locationScope === 'deployed', function ($query) {
                $query->whereHas('location', fn ($locationQuery) => $locationQuery->notInventory());
            })
            ->orderByRaw("CASE WHEN TRIM(COALESCE(type, '')) = '' THEN 1 ELSE 0 END")
            ->orderByRaw("LOWER(TRIM(COALESCE(type, '')))")
            ->orderByRaw("LOWER(TRIM(COALESCE(model, '')))")
            ->orderByRaw("LOWER(TRIM(COALESCE(serial_number, '')))")
            ->orderBy('id');

        foreach ($machines->lazy(200) as $machine) {
            yield [
                $machine->serial_number,
                $machine->type,
                $machine->model,
                $machine->status,
                $machine->installed_on?->format('Y-m-d'),
                $machine->location?->location_name,
            ];
        }
    }

    /**
     * @return iterable<int, array<int, mixed>>
     */
    protected function locationRows(Account $account, array $filters = []): iterable
    {
        $search = trim((string) ($filters['search'] ?? ''));

        $locations = Location::query()
            ->where('account_id', $account->id)
            ->notInventory()
            ->with([
                'primaryRouteLocation.route',
                'primaryLocationContact.contact',
            ])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($locationQuery) use ($search) {
                    $locationQuery
                        ->where('location_name', 'like', '%'.$search.'%')
                        ->orWhere('city', 'like', '%'.$search.'%')
                        ->orWhereHas('contacts', function ($contactQuery) use ($search) {
                            $contactQuery
                                ->where('first_name', 'like', '%'.$search.'%')
                                ->orWhere('last_name', 'like', '%'.$search.'%')
                                ->orWhere('organization', 'like', '%'.$search.'%')
                                ->orWhere('email', 'like', '%'.$search.'%')
                                ->orWhere('phone', 'like', '%'.$search.'%')
                                ->orWhere('mobile_phone', 'like', '%'.$search.'%');
                        });
                });
            })
            ->orderBy('id', 'desc');

        foreach ($locations->lazy(200) as $location) {
            $primaryContact = $location->primaryLocationContact?->contact;

            yield [
                $location->location_name,
                $location->address,
                $location->city,
                $location->state,
                $location->zip_code,
                $location->primaryRouteLocation?->route?->route_name,
                $primaryContact?->display_name,
                $primaryContact?->email,
                $primaryContact?->phone ?: $primaryContact?->mobile_phone,
            ];
        }
    }

    /**
     * @return iterable<int, array<int, mixed>>
     */
    protected function contactRows(Account $account, array $filters = []): iterable
    {
        $search = trim((string) ($filters['search'] ?? ''));

        $contacts = Contact::query()
            ->where('account_id', $account->id)
            ->with([
                'locationContacts' => fn ($query) => $query
                    ->where('account_id', $account->id)
                    ->with([
                        'location' => fn ($locationQuery) => $locationQuery->where('account_id', $account->id),
                    ])
                    ->orderBy('id'),
            ])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($contactQuery) use ($search) {
                    $contactQuery
                        ->where('first_name', 'like', '%'.$search.'%')
                        ->orWhere('last_name', 'like', '%'.$search.'%')
                        ->orWhere('organization', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%')
                        ->orWhere('mobile_phone', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id');

        foreach ($contacts->lazy(200) as $contact) {
            if ($contact->locationContacts->isEmpty()) {
                yield [
                    $contact->first_name,
                    $contact->last_name,
                    $contact->organization,
                    $contact->title,
                    $contact->email,
                    $contact->phone,
                    $contact->mobile_phone,
                    null,
                    null,
                    null,
                ];

                continue;
            }

            foreach ($contact->locationContacts as $locationContact) {
                yield [
                    $contact->first_name,
                    $contact->last_name,
                    $contact->organization,
                    $contact->title,
                    $contact->email,
                    $contact->phone,
                    $contact->mobile_phone,
                    $locationContact->location?->location_name,
                    $locationContact->contact_role,
                    $locationContact->is_primary ? '1' : '0',
                ];
            }
        }
    }

    /**
     * @return iterable<int, array<int, mixed>>
     */
    protected function serviceRows(Account $account): iterable
    {
        $services = Service::query()
            ->where('account_id', $account->id)
            ->with(['location', 'warehouse', 'user', 'createdBy', 'closedBy'])
            ->orderBy('service_date')
            ->orderBy('id');

        foreach ($services->lazy(200) as $service) {
            yield [
                $service->id,
                $service->service_type,
                $service->status,
                $service->service_date?->format('Y-m-d'),
                $service->scheduled_at?->format('Y-m-d H:i:s'),
                $service->opened_at?->format('Y-m-d H:i:s'),
                $service->completed_at?->format('Y-m-d H:i:s'),
                $service->closed_at?->format('Y-m-d H:i:s'),
                $service->amount_collected,
                $service->location_id,
                $service->location?->location_name,
                $service->warehouse_id,
                $service->warehouse?->warehouse_name,
                $service->user_id,
                $service->user?->name,
                $service->created_by_user_id,
                $service->createdBy?->name,
                $service->closed_by_user_id,
                $service->closedBy?->name,
                $service->notes,
            ];
        }
    }

    /**
     * @return iterable<int, array<int, mixed>>
     */
    protected function transactionRows(Account $account): iterable
    {
        $transactions = Transaction::query()
            ->where('account_id', $account->id)
            ->with([
                'service',
                'machine.location',
                'bin',
                'product',
            ])
            ->orderBy('transaction_at')
            ->orderBy('id');

        foreach ($transactions->lazy(200) as $transaction) {
            yield [
                $transaction->id,
                $transaction->service_id,
                $transaction->machine_id,
                $transaction->machine?->serial_number,
                $transaction->machine?->model,
                $transaction->machine?->location?->id,
                $transaction->machine?->location?->location_name,
                $transaction->bin_id,
                $transaction->bin?->bin_code,
                $transaction->product_id,
                $transaction->product?->display_name,
                $transaction->product?->sku,
                $transaction->transaction_type,
                $transaction->quantity,
                $transaction->spoilage,
                $transaction->transaction_at?->format('Y-m-d H:i:s'),
                $transaction->price,
                $transaction->unit_cost,
            ];
        }
    }

    /**
     * @return iterable<int, array<int, mixed>>
     */
    protected function purchaseRows(Account $account): iterable
    {
        $purchases = Purchase::query()
            ->where('account_id', $account->id)
            ->with(['vendor', 'warehouse'])
            ->orderBy('purchase_date')
            ->orderBy('id');

        foreach ($purchases->lazy(200) as $purchase) {
            yield [
                $purchase->id,
                $purchase->purchase_date?->format('Y-m-d'),
                $purchase->status,
                $purchase->invoice_number,
                $purchase->vendor_id,
                $purchase->vendor?->vendor_name,
                $purchase->warehouse_id,
                $purchase->warehouse?->warehouse_name,
                $purchase->notes,
                $purchase->created_at?->format('Y-m-d H:i:s'),
            ];
        }
    }

    /**
     * @return iterable<int, array<int, mixed>>
     */
    protected function purchaseItemRows(Account $account): iterable
    {
        $purchaseItems = PurchaseItem::query()
            ->where('account_id', $account->id)
            ->with(['purchase.vendor', 'purchase.warehouse', 'product'])
            ->orderBy('purchase_id')
            ->orderBy('id');

        foreach ($purchaseItems->lazy(200) as $purchaseItem) {
            yield [
                $purchaseItem->id,
                $purchaseItem->purchase_id,
                $purchaseItem->purchase?->invoice_number,
                $purchaseItem->purchase?->purchase_date?->format('Y-m-d'),
                $purchaseItem->purchase?->vendor_id,
                $purchaseItem->purchase?->vendor?->vendor_name,
                $purchaseItem->purchase?->warehouse_id,
                $purchaseItem->purchase?->warehouse?->warehouse_name,
                $purchaseItem->product_id,
                $purchaseItem->product?->display_name,
                $purchaseItem->product?->sku,
                $purchaseItem->quantity,
                $purchaseItem->unit_cost,
                $purchaseItem->line_total,
                $purchaseItem->created_at?->format('Y-m-d H:i:s'),
            ];
        }
    }

    /**
     * @return iterable<int, array<int, mixed>>
     */
    protected function inventoryLedgerRows(Account $account): iterable
    {
        $ledgerRows = InventoryLedger::query()
            ->where('account_id', $account->id)
            ->with(['warehouse', 'product'])
            ->orderBy('movement_at')
            ->orderBy('id');

        foreach ($ledgerRows->lazy(200) as $ledgerRow) {
            yield [
                $ledgerRow->id,
                $ledgerRow->movement_at?->format('Y-m-d H:i:s'),
                $ledgerRow->movement_type,
                $ledgerRow->warehouse_id,
                $ledgerRow->warehouse?->warehouse_name,
                $ledgerRow->product_id,
                $ledgerRow->product?->display_name,
                $ledgerRow->product?->sku,
                $ledgerRow->quantity_delta,
                $ledgerRow->unit_cost,
                $ledgerRow->total_cost,
                $ledgerRow->source_type,
                $ledgerRow->source_id,
                $ledgerRow->notes,
                $ledgerRow->created_at?->format('Y-m-d H:i:s'),
            ];
        }
    }

    /**
     * @param resource $handle
     * @param iterable<int, array<int, mixed>> $rows
     */
    protected function writeCsv($handle, array $headers, iterable $rows): int
    {
        fputcsv($handle, $headers);

        $rowCount = 0;

        foreach ($rows as $row) {
            fputcsv($handle, array_map(fn ($value) => $this->normalizeCsvValue($value), $row));
            $rowCount++;
        }

        return $rowCount;
    }

    protected function normalizeCsvValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }
}
