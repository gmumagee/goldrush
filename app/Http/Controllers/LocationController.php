<?php

namespace App\Http\Controllers;

use App\Models\DataDictionary;
use App\Models\Location;
use App\Models\RouteLocation;
use App\Models\Transaction;
use App\Models\VendingRoute;
use App\Services\DataDictionaryService;
use App\Services\DashboardSalesChartService;
use App\Support\AppDateTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LocationController extends Controller
{
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

        return view('locations.create', compact('routes'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Location::class);

        $accountId = $this->currentAccountId($request);

        $data = $request->validate([
            'route_id' => [
                'nullable',
                'integer',
                Rule::exists('tbl_routes', 'id')->where(fn ($query) => $query->where('account_id', $accountId)),
            ],
            'location_name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'zip_code' => ['nullable', 'string', 'max:20'],
        ]);

        $data['account_id'] = $accountId;

        DB::transaction(function () use ($data, $accountId) {
            $primaryRouteId = isset($data['route_id']) && $data['route_id'] !== null ? (int) $data['route_id'] : null;
            unset($data['route_id']);

            $location = Location::create($data);
            $this->syncPrimaryRouteMembership($accountId, $location, $primaryRouteId);
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
        ]);
    }

    public function edit(Request $request, int $location): View
    {
        $accountId = $this->currentAccountId($request);
        $location = $this->locationForAccount($accountId, $location);
        $this->authorize('update', $location);

        return view('locations.edit', [
            'location' => $location,
            'routes' => $this->routesForAccount($accountId),
            'selectedRouteId' => $location->primaryRouteLocation()->value('route_id'),
        ]);
    }

    public function update(Request $request, int $location): RedirectResponse
    {
        $accountId = $this->currentAccountId($request);
        $location = $this->locationForAccount($accountId, $location);
        $this->authorize('update', $location);

        $data = $request->validate([
            'route_id' => [
                'nullable',
                'integer',
                Rule::exists('tbl_routes', 'id')->where(fn ($query) => $query->where('account_id', $accountId)),
            ],
            'location_name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'zip_code' => ['nullable', 'string', 'max:20'],
        ]);

        DB::transaction(function () use ($location, $data, $accountId) {
            $primaryRouteId = isset($data['route_id']) && $data['route_id'] !== null ? (int) $data['route_id'] : null;
            unset($data['route_id']);

            $location->update($data);
            $this->syncPrimaryRouteMembership($accountId, $location, $primaryRouteId);
        });

        return redirect()->route('locations.show', $location)->with('status', 'Location updated successfully.');
    }

    public function destroy(Request $request, int $location): RedirectResponse
    {
        $accountId = $this->currentAccountId($request);
        $location = $this->locationForAccount($accountId, $location, ['machines', 'services', 'routeLocations', 'documents']);
        $this->authorize('delete', $location);

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

    protected function syncPrimaryRouteMembership(int $accountId, Location $location, ?int $newRouteId): void
    {
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
