<?php

namespace App\Http\Controllers;

use App\Models\DataDictionary;
use App\Models\Location;
use App\Models\LocationAccessHour;
use App\Models\RouteLocation;
use App\Models\Transaction;
use App\Models\VendingRoute;
use App\Services\DataDictionaryService;
use App\Services\DashboardSalesChartService;
use App\Support\EntityValidation;
use App\Support\AppDateTime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LocationController extends Controller
{
    protected const ACCESS_HOUR_DAY_LABELS = [
        1 => 'Mon',
        2 => 'Tue',
        3 => 'Wed',
        4 => 'Thu',
        5 => 'Fri',
        6 => 'Sat',
        0 => 'Sun',
    ];

    public function __construct(
        protected DataDictionaryService $dataDictionaryService,
        protected DashboardSalesChartService $dashboardSalesChartService,
    )
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Location::class);

        $accountId = $this->currentAccountId($request);
        $search = trim((string) $request->string('search'));

        $locations = Location::query()
            ->where('account_id', $accountId)
            ->notInventory()
            ->with(['primaryRouteLocation.route', 'primaryLocationContact.contact'])
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
            ->orderBy('id', 'desc')
            ->paginate(25)
            ->withQueryString();

        return view('locations.index', compact('locations', 'search'));
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Location::class);

        $accountId = $this->currentAccountId($request);

        $routes = $this->routesForAccount($accountId);

        return view('locations.create', [
            'routes' => $routes,
            'accessHourDayLabels' => self::ACCESS_HOUR_DAY_LABELS,
            'accessHourDefaults' => $this->accessHourDefaults(),
            'servicePatternTypeDefault' => '',
            'serviceIntervalCustomDefault' => '',
            'salesTaxRatePercentDefault' => '',
            'commissionRatePercentDefault' => '',
            'commissionOnNetDefault' => false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Location::class);

        $accountId = $this->currentAccountId($request);

        $data = $this->validateLocation($request, $accountId);

        DB::transaction(function () use ($data, $accountId) {
            $primaryRouteId = isset($data['route_id']) && $data['route_id'] !== null ? (int) $data['route_id'] : null;
            $accessHourRows = $data['access_hour_rows'] ?? [];
            unset($data['route_id']);
            unset($data['access_hour_rows']);

            $data['account_id'] = $accountId;
            $location = Location::create($data);
            $this->syncPrimaryRouteMembership($accountId, $location, $primaryRouteId);
            $this->syncAccessHours($accountId, $location, $accessHourRows);
        });

        return redirect()->route('locations.index')->with('status', 'Location created successfully.');
    }

    public function show(Request $request, int $location): View
    {
        $accountId = $this->currentAccountId($request);

        // Load every location detail relationship inside the current account to avoid cross-tenant leaks.
        $location = $this->locationForAccount($accountId, $location, [
            'primaryRouteLocation.route',
            'routes',
            'locationContacts' => fn ($query) => $query
                ->where('account_id', $accountId)
                ->with([
                    'contact' => fn ($contactQuery) => $contactQuery->where('account_id', $accountId),
                ])
                ->orderByDesc('is_primary')
                ->orderBy('id'),
            'primaryLocationContact' => fn ($query) => $query
                ->where('account_id', $accountId)
                ->with([
                    'contact' => fn ($contactQuery) => $contactQuery->where('account_id', $accountId),
                ]),
            'documents' => fn ($query) => $query
                ->where('account_id', $accountId)
                ->with('uploadedBy')
                ->orderByDesc('created_at')
                ->orderByDesc('id'),
            'accessHours' => fn ($query) => $query->where('account_id', $accountId),
            'machines' => fn ($query) => $query
                ->where('account_id', $accountId)
                ->with([
                    'bins' => fn ($binQuery) => $binQuery
                        ->where('account_id', $accountId)
                        ->with([
                            'product' => fn ($productQuery) => $productQuery->where('account_id', $accountId),
                        ])
                        ->orderBy('bin_code')
                        ->orderBy('id'),
                ])
                ->orderBy('type')
                ->orderBy('serial_number')
                ->orderBy('id'),
            'services' => fn ($query) => $query
                ->where('account_id', $accountId)
                ->with(['user', 'closedBy'])
                ->withSum('calculatedSales as sales_total', 'sales_amount')
                ->withCount(['calculatedSales', 'baselineSales'])
                ->withCount('transactions')
                ->orderByDesc('service_date')
                ->orderByDesc('id'),
        ]);
        $this->authorize('view', $location);

        // Build one summary payload so the view does not have to reconstruct contact fallbacks.
        $primaryContact = $location->primaryLocationContact?->contact;
        $cityStateZip = trim(collect([
            $location->city,
            trim(($location->state ?? '').' '.($location->zip_code ?? '')),
        ])->filter()->implode(', '));
        $addressLine = collect([
            $location->address,
            $cityStateZip,
        ])->filter()->implode(', ');
        $primaryContactName = $primaryContact?->display_name;
        $primaryContactPhone = $primaryContact?->phone ?: $primaryContact?->mobile_phone;
        $primaryContactEmail = $primaryContact?->email;

        // Prepare machine inventory rows once so the view can stay query-free and tenant-safe.
        $machineInventoryGroups = $this->buildMachineInventoryGroups($location, $accountId);
        // Build location sales directly from persisted service-sale snapshots so historical machine moves never change past location revenue.
        $locationSalesChart = $this->dashboardSalesChartService->buildForLocation($accountId, (int) $location->id);

        return view('locations.show', [
            'location' => $location,
            'addressLine' => $addressLine,
            'primaryContactName' => $primaryContactName,
            'primaryContactPhone' => $primaryContactPhone,
            'primaryContactEmail' => $primaryContactEmail,
            'machineInventoryGroups' => $machineInventoryGroups,
            'locationContactRoleLabels' => $this->dataDictionaryService->labels(DataDictionary::GROUP_LOCATION_CONTACT_ROLE, $accountId, true),
            'locationDocumentTypeLabels' => $this->dataDictionaryService->labels(DataDictionary::GROUP_LOCATION_DOCUMENT_TYPE, $accountId, true),
            'serviceStatusLabels' => $this->dataDictionaryService->labels(DataDictionary::GROUP_SERVICE_STATUS, $accountId, true),
            'serviceTypeLabels' => $this->dataDictionaryService->labels('service_type', $accountId, true),
            'locationSalesChart' => $locationSalesChart,
            'accessHourRows' => $this->accessHourDisplayRows($location),
            'servicePatternLabel' => $this->servicePatternLabel($location),
            'salesTaxRateLabel' => $location->sales_tax_rate !== null
                ? $this->formatSalesTaxRatePercent((string) $location->sales_tax_rate).'%'
                : 'No tax rate set.',
            'commissionLabel' => $this->commissionLabel($location),
        ]);
    }

    public function edit(Request $request, int $location): View
    {
        $accountId = $this->currentAccountId($request);
        $location = $this->locationForAccount($accountId, $location, ['accessHours' => fn ($query) => $query->where('account_id', $accountId)]);
        $this->authorize('update', $location);

        return view('locations.edit', [
            'location' => $location,
            'routes' => $this->routesForAccount($accountId),
            'selectedRouteId' => $location->isInventory() ? null : $location->primaryRouteLocation()->value('route_id'),
            'accessHourDayLabels' => self::ACCESS_HOUR_DAY_LABELS,
            'accessHourDefaults' => $this->accessHourDefaults($location),
            'servicePatternTypeDefault' => $this->servicePatternType($location),
            'serviceIntervalCustomDefault' => $location->service_interval_days !== null && ! in_array((int) $location->service_interval_days, [7, 14], true)
                ? (string) $location->service_interval_days
                : '',
            'salesTaxRatePercentDefault' => $location->sales_tax_rate !== null
                ? $this->formatSalesTaxRatePercent((string) $location->sales_tax_rate)
                : '',
            'commissionRatePercentDefault' => $location->commission_rate !== null
                ? $this->formatSalesTaxRatePercent((string) $location->commission_rate)
                : '',
            'commissionOnNetDefault' => (bool) $location->commission_on_net,
        ]);
    }

    public function update(Request $request, int $location): RedirectResponse
    {
        $accountId = $this->currentAccountId($request);
        $location = $this->locationForAccount($accountId, $location);
        $this->authorize('update', $location);

        $data = $this->validateLocation($request, $accountId);

        DB::transaction(function () use ($location, $data, $accountId) {
            $primaryRouteId = isset($data['route_id']) && $data['route_id'] !== null ? (int) $data['route_id'] : null;
            $accessHourRows = $data['access_hour_rows'] ?? [];
            unset($data['route_id']);
            unset($data['access_hour_rows']);

            $location->update($data);
            $this->syncPrimaryRouteMembership($accountId, $location, $primaryRouteId);
            $this->syncAccessHours($accountId, $location, $accessHourRows);
        });

        return redirect()->route('locations.show', $location)->with('status', 'Location updated successfully.');
    }

    public function destroy(Request $request, int $location): RedirectResponse
    {
        $accountId = $this->currentAccountId($request);
        $location = $this->locationForAccount($accountId, $location, ['machines', 'services', 'routeLocations', 'documents']);
        $this->authorize('delete', $location);

        if ($location->isInventory()) {
            abort(403, 'Inventory locations cannot be deleted.');
        }

        if ($location->machines()->exists() || $location->services()->exists()) {
            return back()->withErrors([
                'location' => 'Location cannot be deleted because it has machines or services.',
            ]);
        }

        if ($location->routeLocations()->exists()) {
            return back()->withErrors([
                'location' => 'Location cannot be deleted because it is assigned to a route.',
            ]);
        }

        DB::transaction(function () use ($location) {
            foreach ($location->documents as $document) {
                $document->deleteStoredFile();
                $document->delete();
            }

            $location->delete();
        });

        return redirect()->route('locations.index')->with('status', 'Location deleted successfully.');
    }

    protected function buildMachineInventoryGroups(Location $location, int $accountId): Collection
    {
        $machines = $location->machines->values();

        if ($machines->isEmpty()) {
            return collect();
        }

        // Load the newest current-inventory snapshots in one pass so nested machine rows do not create N+1 queries.
        $latestInventoryByBinProduct = $this->latestCurrentInventoryByBinProduct($machines, $accountId);

        return $machines->map(function ($machine) use ($latestInventoryByBinProduct) {
            $snapshotBinCount = 0;

            $binRows = $machine->bins
                ->map(function ($bin) use (
                    $latestInventoryByBinProduct,
                    &$snapshotBinCount
                ) {
                    $product = $bin->product;
                    $inventoryTransaction = null;

                    if ($product !== null) {
                        $inventoryTransaction = $latestInventoryByBinProduct->get(
                            $this->inventorySnapshotKey((int) $bin->id, (int) $product->id)
                        );
                    }

                    $hasInventorySnapshot = $inventoryTransaction !== null;
                    $currentInventory = $hasInventorySnapshot ? (int) $inventoryTransaction->quantity : null;
                    $capacity = (int) ($bin->capacity ?? 0);
                    $sellingPrice = $this->resolveBinSellingPrice($bin, $inventoryTransaction);

                    if ($hasInventorySnapshot) {
                        $snapshotBinCount++;
                    }

                    return [
                        'bin' => $bin,
                        'product' => $product,
                        'capacity' => $capacity,
                        'has_inventory_snapshot' => $hasInventorySnapshot,
                        'current_inventory' => $currentInventory,
                        'selling_price' => $sellingPrice,
                        'inventory_as_of' => $inventoryTransaction?->transaction_at,
                        'inventory_as_of_date' => $inventoryTransaction
                            ? AppDateTime::displayDate($inventoryTransaction->transaction_at)
                            : null,
                        'inventory_as_of_time' => $inventoryTransaction
                            ? AppDateTime::displayTime($inventoryTransaction->transaction_at)
                            : null,
                        'inventory_as_of_iso' => $inventoryTransaction
                            ? AppDateTime::isoDateTime($inventoryTransaction->transaction_at)
                            : null,
                    ];
                })
                ->values();

            return [
                'machine' => $machine,
                'bins' => $binRows,
                'bin_count' => $binRows->count(),
                'snapshot_bin_count' => $snapshotBinCount,
                'total_current_inventory' => $binRows
                    ->filter(fn (array $row) => $row['has_inventory_snapshot'])
                    ->sum('current_inventory'),
            ];
        })->values();
    }

    protected function latestCurrentInventoryByBinProduct(Collection $machines, int $accountId): Collection
    {
        $bins = $machines
            ->flatMap(fn ($machine) => $machine->bins)
            ->values();

        if ($bins->isEmpty()) {
            return collect();
        }

        // Match snapshots by bin and product so historical product swaps do not leak stale inventory into the UI.
        return Transaction::query()
            ->select([
                'id',
                'account_id',
                'machine_id',
                'bin_id',
                'product_id',
                'transaction_type',
                'quantity',
                'transaction_at',
                'price',
                'unit_cost',
            ])
            ->where('account_id', $accountId)
            ->whereIn('machine_id', $machines->pluck('id')->all())
            ->whereIn('bin_id', $bins->pluck('id')->all())
            ->where('transaction_type', Transaction::TYPE_CURRENT_INVENTORY)
            ->whereNotNull('product_id')
            ->whereNotNull('transaction_at')
            ->orderByDesc('transaction_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy(fn (Transaction $transaction) => $this->inventorySnapshotKey(
                (int) $transaction->bin_id,
                (int) $transaction->product_id
            ))
            ->map(fn (Collection $transactions) => $transactions->first());
    }

    protected function inventorySnapshotKey(int $binId, int $productId): string
    {
        return $binId.':'.$productId;
    }

    protected function resolveBinSellingPrice($bin, ?Transaction $inventoryTransaction): ?string
    {
        // Prefer the live bin selling price because that is the customer-facing vend price used by the app.
        if ($bin->price !== null && $bin->price !== '') {
            return (string) $bin->price;
        }

        if ($inventoryTransaction?->price !== null && $inventoryTransaction->price !== '') {
            return (string) $inventoryTransaction->price;
        }

        return null;
    }

    protected function locationForAccount(int $accountId, int $locationId, array $with = []): Location
    {
        return Location::query()
            ->where('account_id', $accountId)
            ->with($with)
            ->findOrFail($locationId);
    }

    protected function routesForAccount(int $accountId)
    {
        return VendingRoute::query()
            ->where('account_id', $accountId)
            ->orderBy('route_name')
            ->get();
    }

    protected function validateLocation(Request $request, int $accountId): array
    {
        $data = $request->validate(array_merge(EntityValidation::locationRules($accountId), [
            'service_pattern_type' => ['nullable', 'string', Rule::in(['weekly', 'biweekly', 'custom'])],
            'service_interval_days_custom' => ['nullable', 'integer', 'min:1'],
            'sales_tax_rate_percent' => ['nullable', 'string', 'regex:/^\d{1,3}(\.\d{1,2})?$/'],
            'commission_rate_percent' => ['nullable', 'string', 'regex:/^\d{1,3}(\.\d{1,2})?$/'],
            'commission_on_net' => ['nullable', 'boolean'],
            'access_hours' => ['nullable', 'array'],
            'access_hours.*.is_open' => ['nullable', 'boolean'],
            'access_hours.*.opens_at' => ['nullable', 'date_format:H:i'],
            'access_hours.*.closes_at' => ['nullable', 'date_format:H:i'],
        ]));

        $data['service_interval_days'] = $this->resolveServiceIntervalDays(
            $data['service_pattern_type'] ?? null,
            $data['service_interval_days_custom'] ?? null
        );
        $data['sales_tax_rate'] = $this->normalizeSalesTaxRatePercent($data['sales_tax_rate_percent'] ?? null);
        $data['commission_rate'] = $this->normalizeSalesTaxRatePercent($data['commission_rate_percent'] ?? null, 'commission_rate_percent', 'Commission rate');
        $data['commission_on_net'] = filter_var($data['commission_on_net'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $data['access_hour_rows'] = $this->normalizeAccessHours($data['access_hours'] ?? []);

        unset(
            $data['service_pattern_type'],
            $data['service_interval_days_custom'],
            $data['sales_tax_rate_percent'],
            $data['commission_rate_percent'],
            $data['access_hours']
        );

        return $data;
    }

    protected function resolveServiceIntervalDays(?string $patternType, mixed $customInterval): ?int
    {
        $patternType = trim((string) $patternType);

        return match ($patternType) {
            'weekly' => 7,
            'biweekly' => 14,
            'custom' => $this->requireCustomServiceInterval($customInterval),
            default => null,
        };
    }

    protected function requireCustomServiceInterval(mixed $customInterval): int
    {
        if ($customInterval === null || $customInterval === '') {
            throw ValidationException::withMessages([
                'service_interval_days_custom' => 'Enter the custom service interval in days.',
            ]);
        }

        return (int) $customInterval;
    }

    protected function normalizeSalesTaxRatePercent(?string $percent, string $field = 'sales_tax_rate_percent', string $label = 'Sales tax rate'): ?string
    {
        $percent = is_string($percent) ? trim($percent) : null;

        if ($percent === null || $percent === '') {
            return null;
        }

        if ((float) $percent < 0 || (float) $percent > 100) {
            throw ValidationException::withMessages([
                $field => $label.' must be between 0 and 100 percent.',
            ]);
        }

        return number_format(((float) $percent) / 100, 4, '.', '');
    }

    protected function normalizeAccessHours(array $submittedHours): array
    {
        $rows = [];
        $errors = [];

        foreach (self::ACCESS_HOUR_DAY_LABELS as $dayOfWeek => $label) {
            $dayInput = $submittedHours[$dayOfWeek] ?? [];
            $isOpen = filter_var($dayInput['is_open'] ?? false, FILTER_VALIDATE_BOOLEAN);

            if (! $isOpen) {
                continue;
            }

            $opensAt = $dayInput['opens_at'] ?? null;
            $closesAt = $dayInput['closes_at'] ?? null;

            if ($opensAt === null || trim((string) $opensAt) === '') {
                $errors["access_hours.$dayOfWeek.opens_at"] = "Opening time is required when {$label} is marked open.";
            }

            if ($closesAt === null || trim((string) $closesAt) === '') {
                $errors["access_hours.$dayOfWeek.closes_at"] = "Closing time is required when {$label} is marked open.";
            }

            if (isset($errors["access_hours.$dayOfWeek.opens_at"]) || isset($errors["access_hours.$dayOfWeek.closes_at"])) {
                continue;
            }

            $normalizedOpensAt = $this->normalizeAccessTimeInput((string) $opensAt, "access_hours.$dayOfWeek.opens_at");
            $normalizedClosesAt = $this->normalizeAccessTimeInput((string) $closesAt, "access_hours.$dayOfWeek.closes_at");

            if ($normalizedClosesAt <= $normalizedOpensAt) {
                $errors["access_hours.$dayOfWeek.closes_at"] = "Closing time must be after opening time for {$label}.";
                continue;
            }

            $rows[] = [
                'day_of_week' => $dayOfWeek,
                'opens_at' => $normalizedOpensAt,
                'closes_at' => $normalizedClosesAt,
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $rows;
    }

    protected function normalizeAccessTimeInput(string $value, string $field): string
    {
        $value = trim($value);

        foreach (['H:i', 'H:i:s'] as $format) {
            $time = CarbonImmutable::createFromFormat('!'.$format, $value);

            if ($time && $time->format($format) === $value) {
                return $time->format('H:i:s');
            }
        }

        throw ValidationException::withMessages([
            $field => 'Use a valid time value.',
        ]);
    }

    protected function syncAccessHours(int $accountId, Location $location, array $accessHourRows): void
    {
        LocationAccessHour::query()
            ->where('account_id', $accountId)
            ->where('location_id', $location->id)
            ->delete();

        if ($accessHourRows === []) {
            return;
        }

        $location->accessHours()->createMany(
            collect($accessHourRows)
                ->map(fn (array $row) => [
                    'account_id' => $accountId,
                    'day_of_week' => $row['day_of_week'],
                    'opens_at' => $row['opens_at'],
                    'closes_at' => $row['closes_at'],
                ])
                ->all()
        );
    }

    protected function accessHourDefaults(?Location $location = null): array
    {
        $existingHours = $location?->accessHours
            ?->keyBy(fn (LocationAccessHour $accessHour) => (int) $accessHour->day_of_week)
            ?? collect();

        return collect(self::ACCESS_HOUR_DAY_LABELS)
            ->mapWithKeys(function (string $label, int $dayOfWeek) use ($existingHours): array {
                /** @var LocationAccessHour|null $accessHour */
                $accessHour = $existingHours->get($dayOfWeek);

                return [
                    $dayOfWeek => [
                        'label' => $label,
                        'is_open' => $accessHour !== null,
                        'opens_at' => $accessHour ? substr((string) $accessHour->opens_at, 0, 5) : '',
                        'closes_at' => $accessHour ? substr((string) $accessHour->closes_at, 0, 5) : '',
                    ],
                ];
            })
            ->all();
    }

    protected function accessHourDisplayRows(Location $location): array
    {
        $existingHours = $location->accessHours->keyBy(fn (LocationAccessHour $accessHour) => (int) $accessHour->day_of_week);

        return collect(self::ACCESS_HOUR_DAY_LABELS)
            ->map(function (string $label, int $dayOfWeek) use ($existingHours): array {
                /** @var LocationAccessHour|null $accessHour */
                $accessHour = $existingHours->get($dayOfWeek);

                return [
                    'label' => $label,
                    'hours' => $accessHour
                        ? $this->formatClockTime((string) $accessHour->opens_at).' - '.$this->formatClockTime((string) $accessHour->closes_at)
                        : 'Closed',
                    'is_open' => $accessHour !== null,
                ];
            })
            ->all();
    }

    protected function servicePatternType(Location $location): string
    {
        return match ((int) ($location->service_interval_days ?? 0)) {
            7 => 'weekly',
            14 => 'biweekly',
            default => $location->service_interval_days !== null ? 'custom' : '',
        };
    }

    protected function servicePatternLabel(Location $location): string
    {
        return match ((int) ($location->service_interval_days ?? 0)) {
            7 => 'Weekly service',
            14 => 'Every 2 weeks',
            default => $location->service_interval_days !== null
                ? 'Services every '.$location->service_interval_days.' days'
                : 'No pattern set.',
        };
    }

    protected function formatSalesTaxRatePercent(string $fraction): string
    {
        return rtrim(rtrim(number_format(((float) $fraction) * 100, 2, '.', ''), '0'), '.');
    }

    protected function commissionLabel(Location $location): string
    {
        if ($location->commission_rate === null) {
            return 'No commission set.';
        }

        return $this->formatSalesTaxRatePercent((string) $location->commission_rate)
            .'% of '
            .($location->commission_on_net ? 'net sales' : 'gross sales');
    }

    protected function formatClockTime(string $time): string
    {
        $parsedTime = CarbonImmutable::createFromFormat('!H:i:s', $time)
            ?: CarbonImmutable::createFromFormat('!H:i', $time);

        return $parsedTime ? $parsedTime->format('g:i A') : $time;
    }

    protected function syncPrimaryRouteMembership(int $accountId, Location $location, ?int $newRouteId): void
    {
        if ($location->isInventory()) {
            if ($newRouteId !== null) {
                throw ValidationException::withMessages([
                    'route_id' => 'Inventory locations cannot be assigned to routes.',
                ]);
            }

            return;
        }

        if ($newRouteId === null) {
            RouteLocation::query()
                ->where('account_id', $accountId)
                ->where('location_id', $location->id)
                ->update([
                    'is_primary' => false,
                ]);

            return;
        }

        $routeLocation = RouteLocation::query()
            ->where('account_id', $accountId)
            ->where('route_id', $newRouteId)
            ->where('location_id', $location->id)
            ->first();

        if (! $routeLocation) {
            $nextStopOrder = (int) RouteLocation::query()
                ->where('account_id', $accountId)
                ->where('route_id', $newRouteId)
                ->max('stop_order') + 1;

            $routeLocation = RouteLocation::create([
                'account_id' => $accountId,
                'route_id' => $newRouteId,
                'location_id' => $location->id,
                'stop_order' => $nextStopOrder,
                'is_primary' => false,
            ]);
        }

        RouteLocation::query()
            ->where('account_id', $accountId)
            ->where('location_id', $location->id)
            ->update([
                'is_primary' => false,
            ]);

        RouteLocation::query()
            ->where('id', $routeLocation->id)
            ->update([
                'is_primary' => true,
            ]);
    }

    protected function renumberStops(int $accountId, int $routeId): void
    {
        $stops = RouteLocation::query()
            ->where('account_id', $accountId)
            ->where('route_id', $routeId)
            ->orderBy('stop_order')
            ->orderBy('id')
            ->get();

        if ($stops->isEmpty()) {
            return;
        }

        RouteLocation::query()
            ->whereIn('id', $stops->pluck('id'))
            ->update([
                'stop_order' => DB::raw('stop_order + 1000'),
            ]);

        foreach ($stops->values() as $index => $stop) {
            RouteLocation::query()
                ->where('id', $stop->id)
                ->update([
                    'stop_order' => $index + 1,
                ]);
        }
    }
}
