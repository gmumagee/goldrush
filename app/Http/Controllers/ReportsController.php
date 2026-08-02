<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\AccountUser;
use App\Models\Location;
use App\Models\Purchase;
use App\Models\Service;
use App\Models\ServiceSale;
use App\Models\User;
use App\Services\CommissionCalculationService;
use App\Services\InventoryCostService;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ReportsController extends Controller
{
    public function inventoryOnHand(Request $request, InventoryCostService $inventoryCostService): View
    {
        $this->ensureReportAccess($request);

        $accountId = $this->currentAccountId($request);
        $warehouses = DB::table('tbl_warehouses')
            ->where('account_id', $accountId)
            ->orderBy('warehouse_name')
            ->get(['id', 'warehouse_name']);
        $inventoryRows = $inventoryCostService->getAccountInventoryOnHand($accountId);

        $warehouseGroups = $warehouses->map(function ($warehouse) use ($inventoryRows) {
            $warehouseRows = $inventoryRows
                ->where('warehouse_id', (int) $warehouse->id)
                ->values();

            $categoryGroups = $warehouseRows
                ->groupBy(function ($row) {
                    $category = trim((string) $row->category);

                    return $category !== '' ? $category : 'Uncategorized';
                })
                ->sortKeys()
                ->map(function ($rows, string $category) {
                    $products = $rows
                        ->map(function ($row) {
                            $quantityOnHand = (int) $row->quantity_on_hand;
                            $inventoryValue = (float) $row->inventory_value;

                            return [
                                'product_id' => (int) $row->product_id,
                                'product_name' => $row->product_name,
                                'display_name' => collect([$row->product_name, $row->size, $row->package_type])
                                    ->filter(fn ($value) => is_string($value) ? trim($value) !== '' : ! is_null($value))
                                    ->implode(' · '),
                                'sku' => $row->sku,
                                'quantity_on_hand' => $quantityOnHand,
                                'inventory_value' => $inventoryValue,
                                'inventory_value_display' => $this->formatCurrencyFromCents($this->toCentsOrZero($inventoryValue)),
                                'average_unit_cost' => (float) $row->average_unit_cost,
                                'status' => $quantityOnHand < 0 ? 'negative' : ($quantityOnHand === 0 ? 'zero' : 'positive'),
                            ];
                        })
                        ->sortBy(fn (array $product) => mb_strtolower($product['product_name']))
                        ->values();

                    return [
                        'category' => $category,
                        'subtotal_quantity' => $products->sum('quantity_on_hand'),
                        'subtotal_value' => round((float) $products->sum('inventory_value'), 4),
                        'subtotal_value_display' => $this->formatCurrencyFromCents($this->toCentsOrZero(round((float) $products->sum('inventory_value'), 4))),
                        'products' => $products,
                    ];
                })
                ->values();

            return [
                'warehouse_id' => (int) $warehouse->id,
                'warehouse_name' => $warehouse->warehouse_name,
                'subtotal_quantity' => $categoryGroups->sum('subtotal_quantity'),
                'subtotal_value' => round((float) $categoryGroups->sum('subtotal_value'), 4),
                'subtotal_value_display' => $this->formatCurrencyFromCents($this->toCentsOrZero(round((float) $categoryGroups->sum('subtotal_value'), 4))),
                'categories' => $categoryGroups,
                'has_inventory' => $categoryGroups->isNotEmpty(),
            ];
        });

        return view('reports.inventory-on-hand', [
            'warehouseGroups' => $warehouseGroups,
            'grandTotalQuantity' => $warehouseGroups->sum('subtotal_quantity'),
            'grandTotalValue' => round((float) $warehouseGroups->sum('subtotal_value'), 4),
            'grandTotalValueDisplay' => $this->formatCurrencyFromCents($this->toCentsOrZero(round((float) $warehouseGroups->sum('subtotal_value'), 4))),
            'hasData' => $inventoryRows->isNotEmpty(),
        ]);
    }

    public function purchasesByVendor(Request $request): View
    {
        $this->ensureReportAccess($request);

        $accountId = $this->currentAccountId($request);
        $filters = $this->validatedPurchasesByVendorFilters($request, $accountId);
        $vendors = $this->reportVendors($accountId);

        $purchases = Purchase::query()
            ->where('account_id', $accountId)
            ->with([
                'vendor:id,vendor_name',
                'warehouse:id,warehouse_name',
            ])
            ->withSum('items as total_amount', 'line_total')
            ->whereDate('purchase_date', '>=', $filters['date_from'])
            ->whereDate('purchase_date', '<=', $filters['date_to'])
            ->whereRaw('LOWER(TRIM(status)) = ?', [mb_strtolower(Purchase::STATUS_POSTED)])
            ->when($filters['vendor_id'] !== null, fn (Builder $query) => $query->where('vendor_id', $filters['vendor_id']))
            ->orderBy('purchase_date')
            ->orderBy('id')
            ->get(['id', 'vendor_id', 'warehouse_id', 'purchase_date', 'status']);

        $vendorGroups = $purchases
            ->groupBy(fn (Purchase $purchase) => $purchase->vendor_id === null ? 'no-vendor' : (string) $purchase->vendor_id)
            ->map(function ($vendorPurchases, string $vendorKey) {
                $totalCents = $vendorPurchases->reduce(
                    fn (int $carry, Purchase $purchase) => $carry + $this->toCentsOrZero($purchase->total_amount ?? '0'),
                    0
                );

                return [
                    'vendor_key' => $vendorKey,
                    'vendor_name' => $vendorKey === 'no-vendor'
                        ? 'No Vendor'
                        : ($vendorPurchases->first()?->vendor?->vendor_name ?? 'Unknown Vendor'),
                    'purchase_count' => $vendorPurchases->count(),
                    'total_cents' => $totalCents,
                    'total_display' => $this->formatCurrencyFromCents($totalCents),
                    'purchases' => $vendorPurchases->map(function (Purchase $purchase) {
                        $purchaseTotalCents = $this->toCentsOrZero($purchase->total_amount ?? '0');

                        return [
                            'id' => $purchase->id,
                            'purchase_date' => $purchase->purchase_date,
                            'purchase_date_display' => \App\Support\AppDateTime::displayDate($purchase->purchase_date),
                            'warehouse_name' => $purchase->warehouse?->warehouse_name ?? '—',
                            'status' => $purchase->status,
                            'total_cents' => $purchaseTotalCents,
                            'total_display' => $this->formatCurrencyFromCents($purchaseTotalCents),
                        ];
                    })->values(),
                ];
            })
            ->sort(function (array $left, array $right) {
                if ($left['total_cents'] === $right['total_cents']) {
                    return strcmp(mb_strtolower($left['vendor_name']), mb_strtolower($right['vendor_name']));
                }

                return $right['total_cents'] <=> $left['total_cents'];
            })
            ->values();

        $selectedVendor = $filters['vendor_id'] !== null
            ? $vendors->firstWhere('id', $filters['vendor_id'])
            : null;

        return view('reports.purchases-by-vendor', [
            'filters' => $filters,
            'vendors' => $vendors,
            'selectedVendor' => $selectedVendor,
            'vendorGroups' => $vendorGroups,
            'grandTotal' => $this->formatCurrencyFromCents($vendorGroups->sum('total_cents')),
            'hasData' => $vendorGroups->isNotEmpty(),
        ]);
    }

    public function inventoryConsumed(Request $request): View
    {
        $this->ensureReportAccess($request);

        $accountId = $this->currentAccountId($request);
        $filters = $this->validatedProfitLossFilters($request);

        $productTotals = DB::table('tbl_service_sales as service_sales')
            ->join('tbl_products as products', function ($join) use ($accountId) {
                $join->on('products.id', '=', 'service_sales.product_id')
                    ->where('products.account_id', '=', $accountId);
            })
            ->where('service_sales.account_id', $accountId)
            ->whereDate('service_sales.sales_date', '>=', $filters['date_from'])
            ->whereDate('service_sales.sales_date', '<=', $filters['date_to'])
            ->whereRaw('LOWER(TRIM(service_sales.calculation_status)) = ?', [ServiceSale::CALCULATION_CALCULATED])
            ->selectRaw('
                products.id as product_id,
                products.category as product_category,
                products.product_name,
                products.sku,
                COALESCE(SUM(service_sales.units_sold), 0) as units_consumed
            ')
            ->groupBy('products.id', 'products.category', 'products.product_name', 'products.sku')
            ->havingRaw('COALESCE(SUM(service_sales.units_sold), 0) > 0')
            ->orderBy('products.category')
            ->orderBy('products.product_name')
            ->get();

        $categoryGroups = $productTotals
            ->groupBy(function ($row) {
                $category = trim((string) $row->product_category);

                return $category !== '' ? $category : 'Uncategorized';
            })
            ->sortKeys()
            ->map(function ($rows, string $category) {
                $products = $rows
                    ->map(fn ($row) => [
                        'product_id' => (int) $row->product_id,
                        'product_name' => $row->product_name,
                        'sku' => $row->sku,
                        'units_consumed' => (int) $row->units_consumed,
                    ])
                    ->sortBy(fn (array $product) => mb_strtolower($product['product_name']))
                    ->values();

                return [
                    'category' => $category,
                    'subtotal_units' => $products->sum('units_consumed'),
                    'products' => $products,
                ];
            })
            ->values();

        return view('reports.inventory-consumed', [
            'filters' => $filters,
            'categoryGroups' => $categoryGroups,
            'grandTotalUnits' => $categoryGroups->sum('subtotal_units'),
            'hasData' => $categoryGroups->isNotEmpty(),
        ]);
    }

    public function cashFlow(Request $request, CommissionCalculationService $commissionCalculationService): View
    {
        $this->ensureReportAccess($request);

        $accountId = $this->currentAccountId($request);
        $filters = $this->validatedProfitLossFilters($request);

        $cashInCents = $this->toCentsOrZero(
            Service::query()
                ->where('account_id', $accountId)
                ->whereNotNull('amount_collected')
                ->whereDate('service_date', '>=', $filters['date_from'])
                ->whereDate('service_date', '<=', $filters['date_to'])
                ->whereRaw('LOWER(TRIM(service_type)) = ?', [Service::TYPE_LOCATION])
                ->sum('amount_collected')
        );

        $expensesCents = $this->toCentsOrZero(
            Expense::query()
                ->where('account_id', $accountId)
                ->whereDate('expense_date', '>=', $filters['date_from'])
                ->whereDate('expense_date', '<=', $filters['date_to'])
                ->sum('amount')
        );

        $inventoryPurchasesCents = $this->toCentsOrZero(
            DB::table('tbl_purchase_items as purchase_items')
                ->join('tbl_purchases as purchases', function ($join) use ($accountId) {
                    $join->on('purchases.id', '=', 'purchase_items.purchase_id')
                        ->where('purchases.account_id', '=', $accountId);
                })
                ->where('purchase_items.account_id', $accountId)
                ->whereDate('purchases.purchase_date', '>=', $filters['date_from'])
                ->whereDate('purchases.purchase_date', '<=', $filters['date_to'])
                ->whereRaw('LOWER(TRIM(purchases.status)) = ?', [mb_strtolower(Purchase::STATUS_POSTED)])
                ->sum('purchase_items.line_total')
        );

        $commissions = $commissionCalculationService->calculateForAccount($accountId, $filters['date_from'], $filters['date_to']);
        $netCashFlowCents = $cashInCents - $expensesCents - $inventoryPurchasesCents - $commissions['total_cents'];

        return view('reports.cash-flow', [
            'filters' => $filters,
            'statement' => [
                'cash_in' => [
                    'cents' => $cashInCents,
                    'display' => $this->formatCurrencyFromCents($cashInCents),
                ],
                'expenses' => [
                    'cents' => $expensesCents,
                    'display' => $this->formatCurrencyFromCents($expensesCents),
                ],
                'inventory_purchases' => [
                    'cents' => $inventoryPurchasesCents,
                    'display' => $this->formatCurrencyFromCents($inventoryPurchasesCents),
                ],
                'commissions' => [
                    'cents' => $commissions['total_cents'],
                    'display' => $this->formatCurrencyFromCents($commissions['total_cents']),
                    'breakdown' => $commissions['locations'],
                ],
                'net_cash_flow' => [
                    'cents' => $netCashFlowCents,
                    'display' => $this->formatCurrencyFromCents($netCashFlowCents),
                ],
                'has_activity' => $cashInCents !== 0
                    || $expensesCents !== 0
                    || $inventoryPurchasesCents !== 0
                    || $commissions['total_cents'] !== 0,
            ],
        ]);
    }

    public function driverCashTally(Request $request): View
    {
        $this->ensureReportAccess($request);

        $accountId = $this->currentAccountId($request);
        $filters = $this->validatedDriverCashTallyFilters($request, $accountId);
        $drivers = $this->reportDrivers($accountId);

        $selectedDriver = null;

        if ($filters['driver_filter'] === 'unassigned') {
            $selectedDriver = ['id' => 'unassigned', 'name' => 'Unassigned'];
        } elseif ($filters['driver_id'] !== null) {
            $matchedDriver = $drivers->firstWhere('id', $filters['driver_id']);
            $selectedDriver = $matchedDriver !== null
                ? ['id' => $matchedDriver->id, 'name' => $matchedDriver->name]
                : null;
        }

        $services = Service::query()
            ->with([
                'location:id,location_name',
                'user:id,name',
            ])
            ->where('account_id', $accountId)
            ->whereNotNull('amount_collected')
            ->whereDate('service_date', '>=', $filters['date_from'])
            ->whereDate('service_date', '<=', $filters['date_to'])
            ->where('service_type', Service::TYPE_LOCATION)
            ->when($filters['driver_filter'] === 'unassigned', fn (Builder $query) => $query->whereNull('user_id'))
            ->when($filters['driver_id'] !== null, fn (Builder $query) => $query->where('user_id', $filters['driver_id']))
            ->orderBy('service_date')
            ->orderBy('id')
            ->get(['id', 'location_id', 'user_id', 'service_date', 'amount_collected']);

        $groupedTallies = $services
            ->groupBy(fn (Service $service) => $service->user_id === null ? 'unassigned' : (string) $service->user_id)
            ->map(function ($driverServices, string $driverKey) {
                $subtotalCents = $driverServices->reduce(
                    fn (int $carry, Service $service) => $carry + Money::toCents((string) $service->amount_collected),
                    0
                );

                return [
                    'driver_key' => $driverKey,
                    'driver_name' => $driverKey === 'unassigned'
                        ? 'Unassigned'
                        : ($driverServices->first()?->user?->name ?? 'Unknown Driver'),
                    'subtotal_cents' => $subtotalCents,
                    'subtotal_display' => $this->formatCurrencyFromCents($subtotalCents),
                    'services' => $driverServices->values(),
                ];
            })
            ->sort(function (array $left, array $right) {
                if ($left['subtotal_cents'] === $right['subtotal_cents']) {
                    return strcmp(mb_strtolower($left['driver_name']), mb_strtolower($right['driver_name']));
                }

                return $right['subtotal_cents'] <=> $left['subtotal_cents'];
            })
            ->values();

        $grandTotalCents = $groupedTallies->sum('subtotal_cents');

        return view('reports.driver-cash-tally', [
            'filters' => $filters,
            'drivers' => $drivers,
            'selectedDriver' => $selectedDriver,
            'groupedTallies' => $groupedTallies,
            'grandTotal' => $this->formatCurrencyFromCents($grandTotalCents),
            'hasData' => $groupedTallies->isNotEmpty(),
        ]);
    }

    public function cashCollected(Request $request): View
    {
        $this->ensureReportAccess($request);

        $accountId = $this->currentAccountId($request);
        $filters = $this->validatedCashCollectedFilters($request, $accountId);
        $locations = $this->reportLocations($accountId);
        $selectedLocation = $filters['location_id'] !== null
            ? $locations->firstWhere('id', $filters['location_id'])
            : null;

        $services = Service::query()
            ->with(['location:id,location_name'])
            ->where('account_id', $accountId)
            ->whereNotNull('amount_collected')
            ->whereDate('service_date', '>=', $filters['date_from'])
            ->whereDate('service_date', '<=', $filters['date_to'])
            ->where('service_type', Service::TYPE_LOCATION)
            ->when($filters['location_id'] !== null, fn (Builder $query) => $query->where('location_id', $filters['location_id']))
            ->orderBy('service_date')
            ->orderBy('id')
            ->get(['id', 'location_id', 'service_date', 'amount_collected']);

        $groupedCollections = $services
            ->groupBy('location_id')
            ->map(function ($locationServices, $locationId) {
                $subtotalCents = $locationServices->reduce(
                    fn (int $carry, Service $service) => $carry + Money::toCents((string) $service->amount_collected),
                    0
                );

                return [
                    'location_id' => (int) $locationId,
                    'location_name' => $locationServices->first()?->location?->location_name ?? 'Unknown Location',
                    'subtotal_cents' => $subtotalCents,
                    'subtotal_display' => $this->formatCurrencyFromCents($subtotalCents),
                    'services' => $locationServices->values(),
                ];
            })
            ->sort(function (array $left, array $right) {
                if ($left['subtotal_cents'] === $right['subtotal_cents']) {
                    return strcmp(mb_strtolower($left['location_name']), mb_strtolower($right['location_name']));
                }

                return $right['subtotal_cents'] <=> $left['subtotal_cents'];
            })
            ->values();

        $grandTotalCents = $groupedCollections->sum('subtotal_cents');

        return view('reports.cash-collected', [
            'filters' => $filters,
            'locations' => $locations,
            'selectedLocation' => $selectedLocation,
            'groupedCollections' => $groupedCollections,
            'grandTotal' => $this->formatCurrencyFromCents($grandTotalCents),
            'hasData' => $groupedCollections->isNotEmpty(),
        ]);
    }

    public function salesByLocation(Request $request): View
    {
        $this->ensureReportAccess($request);

        $accountId = $this->currentAccountId($request);
        $filters = $this->validatedSalesByLocationFilters($request, $accountId);
        $locations = $this->reportLocations($accountId);
        $selectedLocation = $filters['location_id'] !== null
            ? $locations->firstWhere('id', $filters['location_id'])
            : null;

        if ($filters['location_id'] === null) {
            $locationTotals = Location::query()
                ->where('account_id', $accountId)
                ->notInventory()
                ->whereHas('services', fn (Builder $query) => $this->applySalesReportFilters($query, $accountId, $filters['date_from'], $filters['date_to']))
                ->withSum(
                    ['services as total_amount_collected' => fn (Builder $query) => $this->applySalesReportFilters($query, $accountId, $filters['date_from'], $filters['date_to'])],
                    'amount_collected'
                )
                ->orderByDesc('total_amount_collected')
                ->orderBy('location_name')
                ->get(['id', 'location_name']);

            $grandTotalCents = $locationTotals->reduce(
                fn (int $carry, Location $location) => $carry + Money::toCents((string) ($location->total_amount_collected ?? '0')),
                0
            );

            return view('reports.sales-by-location', [
                'filters' => $filters,
                'locations' => $locations,
                'selectedLocation' => null,
                'locationTotals' => $locationTotals,
                'serviceRows' => collect(),
                'grandTotal' => Money::fromCents($grandTotalCents),
            ]);
        }

        $serviceRows = Service::query()
            ->where('account_id', $accountId)
            ->where('location_id', $filters['location_id'])
            ->whereNotNull('amount_collected')
            ->whereDate('service_date', '>=', $filters['date_from'])
            ->whereDate('service_date', '<=', $filters['date_to'])
            ->where('service_type', Service::TYPE_LOCATION)
            ->orderBy('service_date')
            ->orderBy('id')
            ->get(['id', 'location_id', 'service_date', 'amount_collected']);

        $grandTotalCents = $serviceRows->reduce(
            fn (int $carry, Service $service) => $carry + Money::toCents((string) $service->amount_collected),
            0
        );

        return view('reports.sales-by-location', [
            'filters' => $filters,
            'locations' => $locations,
            'selectedLocation' => $selectedLocation,
            'locationTotals' => collect(),
            'serviceRows' => $serviceRows,
            'grandTotal' => Money::fromCents($grandTotalCents),
        ]);
    }

    public function commission(Request $request): View
    {
        $this->ensureReportAccess($request);

        return view('reports.commission');
    }

    public function profitLoss(Request $request): View
    {
        $this->ensureReportAccess($request);

        $filters = $this->validatedProfitLossFilters($request);
        $statement = $this->buildProfitLossStatement($this->currentAccountId($request), $filters['date_from'], $filters['date_to']);

        return view('reports.profit-loss', [
            'filters' => $filters,
            'statement' => $statement,
        ]);
    }

    public function pnl(Request $request): RedirectResponse
    {
        return redirect()->route('reports.profit-loss', $request->only(['date_from', 'date_to']));
    }

    protected function ensureReportAccess(Request $request): void
    {
        abort_unless($this->currentMembership($request)->canGenerateReports(), 403);
    }

    protected function validatedSalesByLocationFilters(Request $request, int $accountId): array
    {
        $validated = $request->validate([
            'location_id' => [
                'nullable',
                'integer',
                Rule::exists('tbl_locations', 'id')->where(fn ($query) => $query
                    ->where('account_id', $accountId)
                    ->whereNull('is_inventory')),
            ],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $dateTo = ! empty($validated['date_to'])
            ? (string) $validated['date_to']
            : now()->toDateString();
        $dateFrom = ! empty($validated['date_from'])
            ? (string) $validated['date_from']
            : now()->subDays(29)->toDateString();

        if ($dateTo < $dateFrom) {
            throw ValidationException::withMessages([
                'date_to' => 'End date must be on or after the start date.',
            ]);
        }

        return [
            'location_id' => isset($validated['location_id']) ? (int) $validated['location_id'] : null,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];
    }

    protected function validatedCashCollectedFilters(Request $request, int $accountId): array
    {
        $validated = $request->validate([
            'location_id' => [
                'nullable',
                'integer',
                Rule::exists('tbl_locations', 'id')->where(fn ($query) => $query
                    ->where('account_id', $accountId)
                    ->whereNull('is_inventory')),
            ],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $dateTo = ! empty($validated['date_to'])
            ? (string) $validated['date_to']
            : now()->toDateString();
        $dateFrom = ! empty($validated['date_from'])
            ? (string) $validated['date_from']
            : now()->startOfMonth()->toDateString();

        if ($dateTo < $dateFrom) {
            throw ValidationException::withMessages([
                'date_to' => 'End date must be on or after the start date.',
            ]);
        }

        return [
            'location_id' => isset($validated['location_id']) ? (int) $validated['location_id'] : null,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];
    }

    protected function validatedPurchasesByVendorFilters(Request $request, int $accountId): array
    {
        $validated = $request->validate([
            'vendor_id' => [
                'nullable',
                'integer',
                Rule::exists('tbl_vendors', 'id')->where(fn ($query) => $query->where('account_id', $accountId)),
            ],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $dateTo = ! empty($validated['date_to'])
            ? (string) $validated['date_to']
            : now()->toDateString();
        $dateFrom = ! empty($validated['date_from'])
            ? (string) $validated['date_from']
            : now()->startOfMonth()->toDateString();

        if ($dateTo < $dateFrom) {
            throw ValidationException::withMessages([
                'date_to' => 'End date must be on or after the start date.',
            ]);
        }

        return [
            'vendor_id' => isset($validated['vendor_id']) ? (int) $validated['vendor_id'] : null,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];
    }

    protected function validatedDriverCashTallyFilters(Request $request, int $accountId): array
    {
        $validated = $request->validate([
            'driver_filter' => ['nullable', 'string'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $driverFilter = trim((string) ($validated['driver_filter'] ?? ''));
        $driverId = null;

        if ($driverFilter !== '' && $driverFilter !== 'unassigned') {
            if (! ctype_digit($driverFilter)) {
                throw ValidationException::withMessages([
                    'driver_filter' => 'Choose a valid driver.',
                ]);
            }

            $driverId = (int) $driverFilter;

            $isValidDriver = $this->reportDrivers($accountId)
                ->contains(fn (User $driver) => (int) $driver->id === $driverId);

            if (! $isValidDriver) {
                throw ValidationException::withMessages([
                    'driver_filter' => 'Choose a valid driver.',
                ]);
            }
        }

        $dateTo = ! empty($validated['date_to'])
            ? (string) $validated['date_to']
            : now()->toDateString();
        $dateFrom = ! empty($validated['date_from'])
            ? (string) $validated['date_from']
            : now()->startOfMonth()->toDateString();

        if ($dateTo < $dateFrom) {
            throw ValidationException::withMessages([
                'date_to' => 'End date must be on or after the start date.',
            ]);
        }

        return [
            'driver_filter' => $driverFilter,
            'driver_id' => $driverId,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];
    }

    protected function applySalesReportFilters(Builder $query, int $accountId, string $dateFrom, string $dateTo): Builder
    {
        return $query
            ->where('account_id', $accountId)
            ->where('service_type', Service::TYPE_LOCATION)
            ->whereNotNull('amount_collected')
            ->whereDate('service_date', '>=', $dateFrom)
            ->whereDate('service_date', '<=', $dateTo);
    }

    protected function reportLocations(int $accountId)
    {
        return Location::query()
            ->where('account_id', $accountId)
            ->notInventory()
            ->orderBy('location_name')
            ->get(['id', 'location_name']);
    }

    protected function reportDrivers(int $accountId)
    {
        $membershipDrivers = User::query()
            ->select('tbl_users.id', 'tbl_users.name')
            ->join('tbl_account_users', 'tbl_account_users.user_id', '=', 'tbl_users.id')
            ->where('tbl_account_users.account_id', $accountId)
            ->where('tbl_account_users.status', AccountUser::STATUS_ACTIVE)
            ->where('tbl_users.status', User::STATUS_ACTIVE)
            ->whereIn('tbl_account_users.role', [
                AccountUser::ROLE_OWNER,
                AccountUser::ROLE_ADMIN,
                AccountUser::ROLE_MANAGER,
                AccountUser::ROLE_TECHNICIAN,
            ])
            ->distinct()
            ->get();

        $assignedDrivers = User::query()
            ->select('tbl_users.id', 'tbl_users.name')
            ->whereHas('services', fn (Builder $query) => $query
                ->where('account_id', $accountId)
                ->whereNotNull('user_id'))
            ->get();

        return $membershipDrivers
            ->concat($assignedDrivers)
            ->unique(fn (User $driver) => (int) $driver->id)
            ->sortBy(fn (User $driver) => mb_strtolower($driver->name))
            ->values();
    }

    protected function reportVendors(int $accountId)
    {
        return DB::table('tbl_vendors')
            ->where('account_id', $accountId)
            ->orderBy('vendor_name')
            ->get(['id', 'vendor_name']);
    }

    protected function validatedProfitLossFilters(Request $request): array
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $dateTo = ! empty($validated['date_to'])
            ? (string) $validated['date_to']
            : now()->toDateString();
        $dateFrom = ! empty($validated['date_from'])
            ? (string) $validated['date_from']
            : now()->startOfMonth()->toDateString();

        if ($dateTo < $dateFrom) {
            throw ValidationException::withMessages([
                'date_to' => 'End date must be on or after the start date.',
            ]);
        }

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];
    }

    protected function buildProfitLossStatement(int $accountId, string $dateFrom, string $dateTo): array
    {
        $actualCashAmount = Service::query()
            ->where('account_id', $accountId)
            ->whereNotNull('amount_collected')
            ->whereDate('service_date', '>=', $dateFrom)
            ->whereDate('service_date', '<=', $dateTo)
            ->whereRaw('LOWER(TRIM(service_type)) = ?', [Service::TYPE_LOCATION])
            ->sum('amount_collected');
        $actualCashCents = $this->toCentsOrZero($actualCashAmount);

        $calculatedSalesAmount = ServiceSale::query()
            ->where('account_id', $accountId)
            ->whereDate('sales_date', '>=', $dateFrom)
            ->whereDate('sales_date', '<=', $dateTo)
            ->whereRaw('LOWER(TRIM(calculation_status)) = ?', [ServiceSale::CALCULATION_CALCULATED])
            ->sum('sales_amount');
        $calculatedSalesCents = $this->toCentsOrZero($calculatedSalesAmount);

        $cogsAmount = DB::table('tbl_service_sales as service_sales')
            ->leftJoin('tbl_transactions as count_transactions', function ($join) use ($accountId) {
                $join->on('count_transactions.id', '=', 'service_sales.count_transaction_id')
                    ->where('count_transactions.account_id', '=', $accountId);
            })
            ->where('service_sales.account_id', $accountId)
            ->whereDate('service_sales.sales_date', '>=', $dateFrom)
            ->whereDate('service_sales.sales_date', '<=', $dateTo)
            ->whereRaw('LOWER(TRIM(service_sales.calculation_status)) = ?', [ServiceSale::CALCULATION_CALCULATED])
            ->selectRaw('COALESCE(SUM(service_sales.units_sold * COALESCE(count_transactions.unit_cost, 0)), 0) as total_cogs')
            ->value('total_cogs');
        $cogsCents = $this->toCentsOrZero($cogsAmount);

        $expenseTotalsByCategory = Expense::query()
            ->selectRaw('category, COALESCE(SUM(amount), 0) as total_amount')
            ->where('account_id', $accountId)
            ->whereDate('expense_date', '>=', $dateFrom)
            ->whereDate('expense_date', '<=', $dateTo)
            ->groupBy('category')
            ->pluck('total_amount', 'category');

        $expenseBreakdown = collect(Expense::categoryOptions())
            ->map(function (string $label, string $category) use ($expenseTotalsByCategory) {
                $cents = $this->toCentsOrZero($expenseTotalsByCategory[$category] ?? '0');

                return [
                    'category' => $category,
                    'label' => $label,
                    'cents' => $cents,
                    'display' => $this->formatCurrencyFromCents($cents),
                ];
            })
            ->values();

        $totalExpensesCents = $expenseBreakdown->sum('cents');
        $varianceCents = $calculatedSalesCents - $actualCashCents;
        $grossProfitCents = $actualCashCents - $cogsCents;
        $netIncomeCents = $grossProfitCents - $totalExpensesCents;

        return [
            'actual_cash_collected' => [
                'cents' => $actualCashCents,
                'display' => $this->formatCurrencyFromCents($actualCashCents),
            ],
            'calculated_sales' => [
                'cents' => $calculatedSalesCents,
                'display' => $this->formatCurrencyFromCents($calculatedSalesCents),
            ],
            'variance' => [
                'cents' => $varianceCents,
                'display' => $this->formatCurrencyFromCents($varianceCents),
            ],
            'cogs' => [
                'cents' => $cogsCents,
                'display' => $this->formatCurrencyFromCents($cogsCents),
            ],
            'gross_profit' => [
                'cents' => $grossProfitCents,
                'display' => $this->formatCurrencyFromCents($grossProfitCents),
            ],
            'expenses' => [
                'breakdown' => $expenseBreakdown,
                'total_cents' => $totalExpensesCents,
                'total_display' => $this->formatCurrencyFromCents($totalExpensesCents),
            ],
            'net_income' => [
                'cents' => $netIncomeCents,
                'display' => $this->formatCurrencyFromCents($netIncomeCents),
            ],
            'has_activity' => $actualCashCents !== 0 || $calculatedSalesCents !== 0 || $cogsCents !== 0 || $totalExpensesCents !== 0,
        ];
    }

    protected function toCentsOrZero(string|int|float|null $amount): int
    {
        if ($amount === null || $amount === '') {
            return 0;
        }

        return Money::toCents($amount);
    }

    protected function formatCurrencyFromCents(int $cents): string
    {
        $prefix = $cents < 0 ? '-' : '';

        return $prefix.'$'.number_format(abs($cents) / 100, 2);
    }
}
