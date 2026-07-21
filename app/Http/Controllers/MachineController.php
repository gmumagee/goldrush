<?php

namespace App\Http\Controllers;

use App\Models\DataDictionary;
use App\Models\Location;
use App\Models\Machine;
use App\Models\RouteLocation;
use App\Models\VendingRoute;
use App\Services\DataDictionaryService;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MachineController extends Controller
{
    protected const TYPES = ['soda', 'snack', 'combo', 'other'];

    public function __construct(protected DataDictionaryService $dataDictionaryService)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Machine::class);

        $accountId = $this->currentAccountId($request);
        $search = trim((string) $request->string('search'));

        $machines = Machine::query()
            ->where('account_id', $accountId)
            ->with([
                'location' => function ($query) use ($accountId) {
                    $query->where('account_id', $accountId);
                },
            ])
            ->withCount('bins')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($machineQuery) use ($search) {
                    $machineQuery
                        ->where('serial_number', 'like', '%'.$search.'%')
                        ->orWhere('model', 'like', '%'.$search.'%')
                        ->orWhere('status', 'like', '%'.$search.'%');
                });
            })
            // Order nonblank stored types together on the current page so grouping can use the raw tbl_machines.type value without scanning every account machine.
            ->orderByRaw("CASE WHEN TRIM(COALESCE(type, '')) = '' THEN 1 ELSE 0 END")
            ->orderByRaw("LOWER(TRIM(COALESCE(type, '')))")
            // Use model as the closest available machine identity field before falling back to serial number and ID for deterministic ordering.
            ->orderByRaw("LOWER(TRIM(COALESCE(model, '')))")
            ->orderByRaw("LOWER(TRIM(COALESCE(serial_number, '')))")
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        return view('machines.index', [
            'machines' => $machines,
            'machineGroups' => $this->buildMachineGroups($machines->getCollection()),
            'search' => $search,
        ]);
    }

    public function show(Request $request, int $machine, InventoryService $inventoryService): View
    {
        $accountId = $this->currentAccountId($request);

        $machine = $this->machineForAccount($accountId, $machine, [
            'location',
            'bins' => fn ($query) => $query
                ->where('account_id', $accountId)
                ->with([
                    'product' => fn ($productQuery) => $productQuery->where('account_id', $accountId),
                ])
                ->orderBy('bin_code'),
        ]);
        $this->authorize('view', $machine);

        $inventoryByBin = $inventoryService->getCurrentInventoryForMachine($machine);

        return view('machines.show', [
            'machine' => $machine,
            'inventoryByBin' => $inventoryByBin,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Machine::class);

        $accountId = $this->currentAccountId($request);
        $locations = $this->locationsForAccount($accountId);
        $requestedLocationId = $request->integer('location_id');
        $defaultLocationId = $locations->contains('id', $requestedLocationId)
            ? $requestedLocationId
            : $locations->first()?->id;

        return view('machines.create', [
            'locations' => $locations,
            'defaultLocationId' => $defaultLocationId,
            'machineTypes' => self::TYPES,
            'machineStatuses' => $this->dataDictionaryService->options(DataDictionary::GROUP_MACHINE_STATUS, $accountId),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Machine::class);

        $accountId = $this->currentAccountId($request);
        $data = $this->validateMachine($request, $accountId);

        $data['account_id'] = $accountId;
        $data['location_id'] = $data['location_id'] ?? $this->ensureDefaultLocation($accountId)->id;

        $machine = Machine::create($data);

        return redirect()
            ->route('machines.show', $machine)
            ->with('status', 'Machine created successfully.');
    }

    public function edit(Request $request, int $machine): View
    {
        $accountId = $this->currentAccountId($request);
        $machine = $this->machineForAccount($accountId, $machine);
        $this->authorize('update', $machine);

        return view('machines.edit', [
            'machine' => $machine,
            'locations' => $this->locationsForAccount($accountId),
            'defaultLocationId' => $machine->location_id,
            'machineTypes' => self::TYPES,
            'machineStatuses' => $this->dataDictionaryService->options(DataDictionary::GROUP_MACHINE_STATUS, $accountId),
        ]);
    }

    public function update(Request $request, int $machine): RedirectResponse
    {
        $accountId = $this->currentAccountId($request);
        $machine = $this->machineForAccount($accountId, $machine);
        $this->authorize('update', $machine);
        $data = $this->validateMachine($request, $accountId, $machine);

        $data['location_id'] = $data['location_id'] ?? $this->ensureDefaultLocation($accountId)->id;

        $machine->update($data);

        return redirect()
            ->route('machines.show', $machine->id)
            ->with('status', 'Machine updated successfully.');
    }

    public function destroy(Request $request, int $machine): RedirectResponse
    {
        $accountId = $this->currentAccountId($request);
        $machine = $this->machineForAccount($accountId, $machine);
        $this->authorize('delete', $machine);

        if ($machine->bins()->exists() || $machine->services()->exists() || $machine->transactions()->exists()) {
            return back()->withErrors([
                'machine' => 'Machine cannot be deleted because it has bins, services, or transactions.',
            ]);
        }

        $machine->delete();

        return redirect()
            ->route('machines.index')
            ->with('status', 'Machine deleted successfully.');
    }

    protected function validateMachine(Request $request, int $accountId, ?Machine $machine = null): array
    {
        $data = $request->validate([
            'location_id' => [
                'nullable',
                'integer',
                Rule::exists('tbl_locations', 'id')->where(fn ($query) => $query->where('account_id', $accountId)),
            ],
            'type' => ['required', 'string', 'max:100'],
            'serial_number' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('tbl_machines', 'serial_number')
                    ->where(fn ($query) => $query->where('account_id', $accountId))
                    ->ignore($machine?->id),
            ],
            'model' => ['nullable', 'string', 'max:255'],
            'status' => [
                'required',
                'string',
                'max:50',
                $this->activeDictionaryValueRule(DataDictionary::GROUP_MACHINE_STATUS, $accountId),
            ],
            'installed_on' => ['nullable', 'regex:/^\d{2}-\d{2}-\d{4}$/'],
        ]);

        $data['installed_on'] = $this->normalizeDateInput($data['installed_on'] ?? null, 'installed_on', true);

        return $data;
    }

    protected function locationsForAccount(int $accountId)
    {
        return Location::query()
            ->where('account_id', $accountId)
            ->orderBy('location_name')
            ->get();
    }

    protected function ensureDefaultLocation(int $accountId): Location
    {
        $existingLocation = Location::query()
            ->where('account_id', $accountId)
            ->orderBy('id')
            ->first();

        if ($existingLocation) {
            return $existingLocation;
        }

        $route = VendingRoute::query()->firstOrCreate(
            [
                'account_id' => $accountId,
                'route_name' => 'Default Route',
            ],
            [
                'description' => 'Auto-created default route for machine assignment.',
            ]
        );

        $location = Location::query()->firstOrCreate(
            [
                'account_id' => $accountId,
                'location_name' => 'Default Location',
            ],
            [
                'address' => null,
                'city' => null,
                'state' => null,
                'zip_code' => null,
                'contact_name' => null,
                'contact_phone' => null,
                'contact_email' => null,
            ]
        );

        $routeLocation = RouteLocation::query()->firstOrCreate(
            [
                'account_id' => $accountId,
                'route_id' => $route->id,
                'location_id' => $location->id,
            ],
            [
                'stop_order' => (int) RouteLocation::query()
                    ->where('account_id', $accountId)
                    ->where('route_id', $route->id)
                    ->max('stop_order') + 1,
                'is_primary' => false,
            ]
        );

        $hasPrimaryRoute = $location->routeLocations()
            ->where('account_id', $accountId)
            ->where('is_primary', true)
            ->exists();

        if (! $hasPrimaryRoute) {
            $location->routeLocations()
                ->where('account_id', $accountId)
                ->update(['is_primary' => false]);

            RouteLocation::query()
                ->where('id', $routeLocation->id)
                ->update(['is_primary' => true]);
        }

        return $location;
    }

    protected function machineForAccount(int $accountId, int $machineId, array $with = []): Machine
    {
        // Account isolation: every machine CRUD request is resolved inside the
        // selected account before any related location or bin data is loaded.
        return Machine::query()
            ->where('account_id', $accountId)
            ->with($with)
            ->findOrFail($machineId);
    }

    protected function buildMachineGroups(Collection $machines): Collection
    {
        // Group by the persisted tbl_machines.type value so valid nonblank legacy types stay visible without requiring a dictionary lookup.
        $groups = $machines
            ->groupBy(function (Machine $machine): string {
                return $this->resolveMachineTypeGroup($machine->type)['key'];
            })
            ->map(function (Collection $groupedMachines, string $groupKey): array {
                $resolvedGroup = $this->resolveMachineTypeGroup($groupedMachines->first()?->type);

                return [
                    'key' => $groupKey,
                    'label' => $resolvedGroup['label'],
                    'machines' => $groupedMachines
                        ->sort(fn (Machine $left, Machine $right) => $this->compareMachinesForIndex($left, $right))
                        ->values(),
                    'count' => $groupedMachines->count(),
                    'is_uncategorized' => $resolvedGroup['is_uncategorized'],
                ];
            })
            ->values();

        // Keep named machine types alphabetized while always pushing unresolved types into one final Uncategorized accordion.
        return $groups
            ->sort(function (array $left, array $right): int {
                if ($left['is_uncategorized'] !== $right['is_uncategorized']) {
                    return $left['is_uncategorized'] <=> $right['is_uncategorized'];
                }

                return strcasecmp($left['label'], $right['label']);
            })
            ->values();
    }

    protected function resolveMachineTypeGroup(?string $type): array
    {
        $storedType = trim((string) $type);

        if ($storedType === '') {
            return [
                'key' => 'uncategorized',
                'label' => 'Uncategorized',
                'is_uncategorized' => true,
            ];
        }

        return [
            'key' => $storedType,
            'label' => $storedType,
            'is_uncategorized' => false,
        ];
    }

    protected function compareMachinesForIndex(Machine $left, Machine $right): int
    {
        // Sort each accordion consistently even when machines share the same type on a paginated page.
        foreach ([
            mb_strtolower(trim((string) $left->model)) <=> mb_strtolower(trim((string) $right->model)),
            mb_strtolower(trim((string) $left->serial_number)) <=> mb_strtolower(trim((string) $right->serial_number)),
            $left->id <=> $right->id,
        ] as $comparison) {
            if ($comparison !== 0) {
                return $comparison;
            }
        }

        return 0;
    }
}
