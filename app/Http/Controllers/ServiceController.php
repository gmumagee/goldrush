<?php

namespace App\Http\Controllers;

use App\Models\AccountUser;
use App\Models\CalendarEvent;
use App\Models\DataDictionary;
use App\Models\Location;
use App\Models\Machine;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CalendarService;
use App\Services\DataDictionaryService;
use App\Services\FinalizeServiceSales;
use App\Services\InventoryCostService;
use App\Services\WarehouseInventoryService;
use App\Support\CurrentAccountMembershipResolver;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function __construct(
        protected DataDictionaryService $dataDictionaryService,
        protected CalendarService $calendarService,
        protected FinalizeServiceSales $finalizeServiceSales,
    )
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Service::class);

        $accountId = $this->currentAccountId($request);
        $currentMembership = app(CurrentAccountMembershipResolver::class)->forUser($request->user());
        $technicianUserId = $currentMembership?->isTechnician()
            ? (int) $request->user()->id
            : null;

        $pendingServices = Service::query()
            ->where('account_id', $accountId)
            ->with(['location.primaryRouteLocation.route', 'warehouse', 'user', 'closedBy'])
            ->whereIn('status', [Service::STATUS_AWAITING, 'awaiting service'])
            ->orderBy('service_date')
            ->orderBy('id')
            ->get();

        $completedServicesAwaitingMoney = Service::query()
            ->where('account_id', $accountId)
            ->where('service_type', Service::TYPE_LOCATION)
            ->with(['location.primaryRouteLocation.route', 'warehouse', 'user', 'closedBy'])
            ->where('status', Service::STATUS_COMPLETED)
            ->whereNull('amount_collected')
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->get();

        $allServices = Service::query()
            ->where('account_id', $accountId)
            ->when($technicianUserId !== null, fn ($query) => $query->where('user_id', $technicianUserId))
            ->with(['location.primaryRouteLocation.route', 'warehouse', 'user', 'closedBy'])
            ->orderByDesc('service_date')
            ->orderByDesc('id')
            ->get();

        return view('services.index', [
            'pendingServicesByLocation' => $this->groupServicesByLocation($pendingServices),
            'completedServicesByLocation' => $this->groupServicesByLocation($completedServicesAwaitingMoney),
            'allServicesByLocation' => $this->groupServicesByLocation($allServices),
            'pendingServicesCount' => $pendingServices->count(),
            'completedServicesCount' => $completedServicesAwaitingMoney->count(),
            'allServicesCount' => $allServices->count(),
            'serviceStatusLabels' => $this->dataDictionaryService->labels(DataDictionary::GROUP_SERVICE_STATUS, $accountId, true),
            'serviceTypeLabels' => $this->dataDictionaryService->labels(DataDictionary::GROUP_SERVICE_TYPE, $accountId, true),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Service::class);

        $accountId = $this->currentAccountId($request);
        $locations = $this->locationsForAccount($accountId);
        $requestedLocationId = $request->integer('location_id');
        $selectedLocationId = $locations->contains('id', $requestedLocationId) ? $requestedLocationId : null;

        $serviceTypes = collect($this->serviceTypesForAccount($accountId))
            ->filter(fn (string $label, string $value) => Gate::forUser($request->user())->allows('create', [Service::class, $value]))
            ->all();

        return view('services.create', [
            'locations' => $locations,
            'serviceTypes' => $serviceTypes,
            'warehouses' => $this->warehousesForAccount($accountId),
            'users' => $this->assignableUsersForAccount($accountId)->get(),
            'currentUser' => $request->user(),
            'selectedLocationId' => $selectedLocationId,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $accountId = $this->currentAccountId($request);
        $data = $this->validateService($request, $accountId);
        $this->authorize('create', [Service::class, $data['service_type']]);

        $assignedUserId = isset($data['user_id']) && $data['user_id'] !== null
            ? (int) $data['user_id']
            : (int) $request->user()->id;

        $this->ensureUserBelongsToAccount($accountId, $assignedUserId);

        $service = DB::transaction(function () use ($accountId, $data, $assignedUserId, $request) {
            $isLocationService = strcasecmp((string) $data['service_type'], Service::TYPE_LOCATION) === 0;

            $service = Service::create([
                'account_id' => $accountId,
                'location_id' => (int) $data['location_id'],
                'warehouse_id' => $isLocationService ? (int) $data['warehouse_id'] : null,
                'user_id' => $assignedUserId,
                'created_by_user_id' => (int) $request->user()->id,
                'closed_by_user_id' => null,
                'service_type' => $data['service_type'],
                'notes' => $data['notes'] ?? null,
                'service_date' => $data['service_date'],
                'scheduled_at' => null,
                'opened_at' => null,
                'completed_at' => null,
                'closed_at' => null,
                'amount_collected' => null,
                'status' => Service::STATUS_AWAITING,
            ]);

            $this->calendarService->createServiceEvent($service, (int) $request->user()->id);

            return $service;
        });

        return redirect()
            ->route('services.show', $service->id)
            ->with('status', 'Service created successfully. Service calendar event created.');
    }

    public function show(Request $request, int $service): View
    {
        $accountId = $this->currentAccountId($request);

        $service = $this->resolveService($request, $service, [
            'location.primaryRouteLocation.route',
            'warehouse',
            'location.machines' => fn ($query) => $query->orderBy('type')->orderBy('id'),
            'location.machines.bins',
            'user',
            'createdBy',
            'closedBy',
            'calendarEvents',
            // Keep the sales breakdown inside one account-scoped eager load to avoid Blade-side queries.
            'sales' => fn ($query) => $query
                ->where('account_id', $accountId)
                ->with(['product', 'machine', 'bin'])
                ->orderBy('machine_id')
                ->orderBy('bin_id')
                ->orderBy('product_id')
                ->orderBy('id'),
        ]);
        $this->authorize('view', $service);
        $service->loadSum('calculatedSales as sales_total', 'sales_amount');
        $service->loadCount(['calculatedSales', 'baselineSales']);
        $service->loadCount('transactions');

        $transactionsByDateAndType = $this->groupTransactionsForService($service);
        $machineSalesGroups = $this->groupSalesByMachine($service);

        return view('services.show', [
            'service' => $service,
            'machineSalesGroups' => $machineSalesGroups,
            'transactionsByDateAndType' => $transactionsByDateAndType,
            'serviceStatusLabels' => $this->dataDictionaryService->labels(DataDictionary::GROUP_SERVICE_STATUS, $service->account_id, true),
            'serviceTypeLabels' => $this->dataDictionaryService->labels(DataDictionary::GROUP_SERVICE_TYPE, $service->account_id, true),
            'serviceCalendarEvent' => $service->calendarEvents->first(),
        ]);
    }

    public function edit(Request $request, int $service): View|RedirectResponse
    {
        $service = $this->resolveService($request, $service, ['location', 'user']);
        $this->authorize('update', $service);

        if ($service->isServiceClosed()) {
            return redirect()
                ->route('services.show', $service)
                ->withErrors(['service' => 'Closed services are read-only.']);
        }

        return view('services.edit', [
            'service' => $service,
            'locations' => $this->locationsForAccount($service->account_id),
            'serviceTypes' => $this->serviceTypesForAccount($service->account_id),
            'warehouses' => $this->warehousesForAccount($service->account_id),
            'users' => $this->assignableUsersForAccount($service->account_id)->get(),
            'serviceStatusLabels' => $this->dataDictionaryService->labels(DataDictionary::GROUP_SERVICE_STATUS, $service->account_id, true),
        ]);
    }

    public function update(Request $request, int $service): RedirectResponse
    {
        $service = $this->resolveService($request, $service);
        $this->authorize('update', $service);

        if ($service->isServiceClosed()) {
            return back()->withErrors(['service' => 'Closed services are read-only.']);
        }

        $accountId = $this->currentAccountId($request);
        $data = $this->validateService($request, $accountId, false);
        $assignedUserId = isset($data['user_id']) && $data['user_id'] !== null
            ? (int) $data['user_id']
            : null;

        if ($assignedUserId !== null) {
            $this->ensureUserBelongsToAccount($accountId, $assignedUserId);
        }

        if (
            strcasecmp((string) $data['service_type'], Service::TYPE_MAINTENANCE) === 0
            && $service->transactions()->exists()
        ) {
            throw ValidationException::withMessages([
                'service_type' => 'Services with inventory transactions cannot be changed to Maintenance Service.',
            ]);
        }

        if (
            strcasecmp((string) $data['service_type'], Service::TYPE_MAINTENANCE) === 0
            && $service->isServiceCompleted()
        ) {
            throw ValidationException::withMessages([
                'service_type' => 'A completed location service cannot be changed to Maintenance Service.',
            ]);
        }

        DB::transaction(function () use ($service, $data, $assignedUserId) {
            $isLocationService = strcasecmp((string) $data['service_type'], Service::TYPE_LOCATION) === 0;

            $service->update([
                'location_id' => (int) $data['location_id'],
                'warehouse_id' => $isLocationService ? (int) $data['warehouse_id'] : null,
                'user_id' => $assignedUserId,
                'service_type' => $data['service_type'],
                'notes' => $data['notes'] ?? null,
                'service_date' => $data['service_date'],
                'scheduled_at' => null,
                'completed_at' => $isLocationService ? $service->completed_at : null,
                'amount_collected' => $isLocationService ? $service->amount_collected : null,
            ]);

            $this->calendarService->updateServiceEvent($service->refresh());
        });

        return redirect()
            ->route('services.show', $service)
            ->with('status', 'Service updated successfully.');
    }

    public function destroy(Request $request, int $service): RedirectResponse
    {
        $service = $this->resolveService($request, $service);
        $authorization = Gate::inspect('delete', $service);

        if ($authorization->denied()) {
            if ($service->isMaintenanceService() && $service->isServiceClosed()) {
                return back()->withErrors([
                    'service' => $authorization->message() ?: 'Closed maintenance services cannot be deleted.',
                ]);
            }

            abort(403, $authorization->message() ?: 'This action is unauthorized.');
        }

        if ($service->transactions()->exists()) {
            return back()->withErrors([
                'service' => 'Service cannot be deleted because it has transactions.',
            ]);
        }

        DB::transaction(function () use ($service) {
            $this->calendarService->deleteServiceEvent($service);
            $service->delete();
        });

        return redirect()
            ->route('services.index')
            ->with('status', 'Service deleted successfully.');
    }

    public function open(Request $request, int $service): RedirectResponse
    {
        $service = $this->resolveService($request, $service);
        $this->authorize('update', $service);
        $this->ensureLocationService($service, 'This action is only valid for location services.');
        $this->ensureAwaitingService($service);

        $service->update([
            'status' => Service::STATUS_OPEN,
            'opened_at' => now(),
            'completed_at' => null,
            'closed_at' => null,
            'closed_by_user_id' => null,
            'amount_collected' => null,
        ]);

        return redirect()
            ->route('services.show', $service->id)
            ->with('status', 'Service opened.');
    }

    public function openMaintenance(Request $request, int $service): RedirectResponse
    {
        $service = $this->resolveService($request, $service);
        $this->authorize('update', $service);
        $this->ensureMaintenanceService($service, 'This action is only valid for maintenance services.');
        $this->ensureAwaitingService($service);

        $service->update([
            'status' => Service::STATUS_OPEN,
            'opened_at' => now(),
            'completed_at' => null,
            'closed_at' => null,
            'closed_by_user_id' => null,
            'amount_collected' => null,
        ]);

        return redirect()
            ->route('services.show', $service->id)
            ->with('status', 'Maintenance service opened.');
    }

    public function complete(Request $request, int $service): RedirectResponse
    {
        $service = $this->resolveService($request, $service);
        $this->authorize('finalize', $service);
        $this->ensureLocationService($service, 'This action is only valid for location services.');
        $this->ensureServiceOpen($service);

        DB::transaction(function () use ($service, $request) {
            $service = Service::query()
                ->where('account_id', $service->account_id)
                ->lockForUpdate()
                ->findOrFail($service->id);

            $this->ensureLocationService($service, 'This action is only valid for location services.');
            $this->ensureServiceOpen($service);
            $completedAt = now();
            $result = $this->finalizeServiceSales->finalize($service, $completedAt);

            if ($result['errors'] !== []) {
                throw ValidationException::withMessages([
                    'service' => $result['errors'],
                ]);
            }

            $service->update([
                'status' => Service::STATUS_COMPLETED,
                'completed_at' => $completedAt,
                'closed_at' => null,
                'closed_by_user_id' => null,
                'amount_collected' => null,
            ]);

            $this->calendarService->updateServiceEvent($service->refresh(), (int) $request->user()->id);
        });

        return redirect()
            ->route('services.show', $service->id)
            ->with('status', 'Service completed.');
    }

    public function closeMaintenance(Request $request, int $service): RedirectResponse
    {
        $service = $this->resolveService($request, $service);
        $this->authorize('finalize', $service);
        $this->ensureMaintenanceService($service, 'This action is only valid for maintenance services.');
        $this->ensureServiceOpen($service);
        $data = $request->validate([
            'notes' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($service, $data, $request) {
            $service->update([
                'status' => Service::STATUS_CLOSED,
                'notes' => $data['notes'] ?? null,
                'closed_at' => now(),
                'closed_by_user_id' => (int) $request->user()->id,
                'completed_at' => null,
                'amount_collected' => null,
            ]);

            $this->calendarService->updateServiceEvent($service->refresh(), (int) $request->user()->id);
        });

        return redirect()
            ->route('services.show', $service->id)
            ->with('status', 'Maintenance service closed.');
    }

    public function editAmountCollected(Request $request, int $service): View
    {
        $service = $this->resolveService($request, $service, ['location.primaryRouteLocation.route', 'user', 'closedBy']);
        $this->authorize('finalize', $service);
        $service->loadSum('calculatedSales as sales_total', 'sales_amount');
        $service->loadCount(['calculatedSales', 'baselineSales']);
        $this->ensureLocationService($service, 'Amount collected is only available for location services.');
        $this->ensureAwaitingAmountCollected($service);

        return view('services.amount-collected', [
            'service' => $service,
        ]);
    }

    public function updateAmountCollected(Request $request, int $service): RedirectResponse
    {
        $service = $this->resolveService($request, $service, ['location.machines.bins']);
        $this->authorize('finalize', $service);
        $this->ensureLocationService($service, 'Amount collected is only available for location services.');
        $this->ensureAwaitingAmountCollected($service);
        $data = $request->validate([
            'amount_collected' => ['required', 'numeric', 'min:0'],
        ]);

        $salesCount = $service->sales()->where('account_id', $service->account_id)->count();
        $locationHasBins = $service->location !== null
            && $service->location->machines->contains(fn ($machine) => $machine->bins->isNotEmpty());

        if ($salesCount === 0 && $locationHasBins) {
            return back()->withErrors([
                'service' => 'Finalized sales records are required before closing this service.',
            ]);
        }

        DB::transaction(function () use ($service, $data, $request) {
            $service->update([
                'status' => Service::STATUS_CLOSED,
                'closed_at' => now(),
                'closed_by_user_id' => (int) $request->user()->id,
                'amount_collected' => $data['amount_collected'],
            ]);

            $this->calendarService->updateServiceEvent($service->refresh(), (int) $request->user()->id);
        });

        return redirect()
            ->route('services.show', $service->id)
            ->with('status', 'Amount collected recorded and service closed.');
    }

    public function countMachine(Request $request, int $service, int $machine): View
    {
        $service = $this->resolveService($request, $service, ['location', 'user']);
        $this->authorize('update', $service);
        $this->ensureSupportsInventoryTransactions($service);
        $this->ensureServiceOpen($service);

        $machine = $this->resolveMachineForService($service, $machine, [
            'location',
            'bins' => fn ($query) => $query->with('product')->orderBy('bin_code'),
        ]);

        // Prefill the latest count row so technicians can correct counts and spoilage without creating duplicates.
        $countTransactionsByBin = Transaction::query()
            ->where('account_id', $service->account_id)
            ->where('service_id', $service->id)
            ->where('machine_id', $machine->id)
            ->where('transaction_type', Transaction::TYPE_COUNT)
            ->orderByDesc('transaction_at')
            ->orderByDesc('id')
            ->get()
            ->unique('bin_id')
            ->keyBy('bin_id');

        return view('services.count-machine', [
            'service' => $service,
            'machine' => $machine,
            'countTransactionsByBin' => $countTransactionsByBin,
        ]);
    }

    public function storeCount(Request $request, int $service, int $machine, InventoryCostService $inventoryCostService): RedirectResponse
    {
        $service = $this->resolveService($request, $service);
        $this->authorize('update', $service);
        $this->ensureSupportsInventoryTransactions($service);
        $this->ensureServiceOpen($service);

        $machine = $this->resolveMachineForService($service, $machine, [
            'bins' => fn ($query) => $query->with('product')->orderBy('bin_code'),
        ]);

        $counts = $this->validateBinCounts($request, $machine);

        DB::transaction(function () use ($service, $machine, $counts, $inventoryCostService) {
            foreach ($machine->bins as $bin) {
                // Update the existing count row per bin so corrections stay idempotent until service completion.
                Transaction::query()->updateOrCreate(
                    [
                        'account_id' => $service->account_id,
                        'service_id' => $service->id,
                        'machine_id' => $bin->machine_id,
                        'bin_id' => $bin->id,
                        'product_id' => $bin->product_id,
                        'transaction_type' => Transaction::TYPE_COUNT,
                    ],
                    [
                        'quantity' => (int) $counts[$bin->id]['quantity'],
                        'spoilage' => (int) $counts[$bin->id]['spoilage'],
                        'transaction_at' => now(),
                        'price' => $bin->price,
                        'unit_cost' => $inventoryCostService->getUnitCostForCount(
                            $service->account_id,
                            $service->warehouse_id ? (int) $service->warehouse_id : null,
                            $bin->id,
                            $bin->product_id ? (int) $bin->product_id : null,
                        ),
                    ]
                );
            }
        });

        return redirect()
            ->route('services.show', $service->id)
            ->with('status', 'Machine count recorded successfully.');
    }

    public function fillMachine(Request $request, int $service, int $machine): View
    {
        $service = $this->resolveService($request, $service, ['location', 'user']);
        $this->authorize('update', $service);
        $this->ensureSupportsInventoryTransactions($service);
        $this->ensureServiceOpen($service);

        $machine = $this->resolveMachineForService($service, $machine, [
            'location',
            'bins' => fn ($query) => $query->with('product')->orderBy('bin_code'),
        ]);

        return view('services.fill-machine', [
            'service' => $service,
            'machine' => $machine,
        ]);
    }

    public function storeFill(Request $request, int $service, int $machine, WarehouseInventoryService $warehouseInventoryService): RedirectResponse
    {
        $service = $this->resolveService($request, $service);
        $this->authorize('update', $service);
        $this->ensureSupportsInventoryTransactions($service);
        $this->ensureServiceOpen($service);

        $machine = $this->resolveMachineForService($service, $machine, [
            'bins' => fn ($query) => $query->with('product')->orderBy('bin_code'),
        ]);

        $quantities = $this->validateBinQuantities($request, $machine, false);

        $warehouseInventoryService->createFillTransaction(
            $service,
            $machine->bins->map(function ($bin) use ($quantities) {
                return [
                    'machine_id' => $bin->machine_id,
                    'bin_id' => $bin->id,
                    'bin_code' => $bin->bin_code,
                    'product_id' => $bin->product_id,
                    'product_name' => $bin->product?->product_name,
                    'quantity' => (int) ($quantities[$bin->id] ?? 0),
                    'price' => $bin->price,
                    'transaction_at' => now(),
                ];
            })->all(),
        );

        return redirect()
            ->route('services.show', $service->id)
            ->with('status', 'Machine fill recorded successfully.');
    }

    protected function validateService(Request $request, int $accountId, bool $creating = true): array
    {
        $isLocationService = strcasecmp(trim((string) $request->input('service_type')), Service::TYPE_LOCATION) === 0;

        $data = $request->validate([
            'location_id' => [
                'required',
                'integer',
                Rule::exists('tbl_locations', 'id')->where(fn ($query) => $query->where('account_id', $accountId)),
            ],
            'warehouse_id' => [
                Rule::requiredIf($isLocationService),
                'nullable',
                'integer',
                Rule::exists('tbl_warehouses', 'id')->where(fn ($query) => $query->where('account_id', $accountId)),
            ],
            'service_date' => ['required', 'date'],
            'service_type' => [
                'required',
                'string',
                'max:50',
                $this->activeDictionaryValueRule(DataDictionary::GROUP_SERVICE_TYPE, $accountId),
            ],
            'user_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string'],
        ]);

        $data['service_date'] = CarbonImmutable::parse($data['service_date'])->toDateString();
        $data['warehouse_id'] = $isLocationService ? (int) ($data['warehouse_id'] ?? 0) : null;

        return $data;
    }

    protected function resolveService(Request $request, int $serviceId, array $with = []): Service
    {
        // Account isolation: every service request is scoped to the selected
        // account so a user cannot open or modify another account's service.
        return Service::query()
            ->where('account_id', $this->currentAccountId($request))
            ->with($with)
            ->findOrFail($serviceId);
    }

    protected function resolveMachineForService(Service $service, int $machineId, array $with = []): Machine
    {
        // Location isolation: count and fill actions only accept machines that
        // belong to the same account and the same service location.
        return Machine::query()
            ->where('account_id', $service->account_id)
            ->where('location_id', $service->location_id)
            ->with($with)
            ->findOrFail($machineId);
    }

    protected function locationsForAccount(int $accountId)
    {
        return Location::query()
            ->where('account_id', $accountId)
            ->with('primaryRouteLocation.route')
            ->orderBy('location_name')
            ->get();
    }

    protected function warehousesForAccount(int $accountId)
    {
        return \App\Models\Warehouse::query()
            ->where('account_id', $accountId)
            ->orderBy('warehouse_name')
            ->get();
    }

    protected function assignableUsersForAccount(int $accountId)
    {
        return User::query()
            ->select('tbl_users.*')
            ->join('tbl_account_users', 'tbl_account_users.user_id', '=', 'tbl_users.id')
            ->where('tbl_account_users.account_id', $accountId)
            ->where('tbl_account_users.status', AccountUser::STATUS_ACTIVE)
            ->where('tbl_users.status', User::STATUS_ACTIVE)
            ->distinct()
            ->orderBy('tbl_users.name');
    }

    protected function ensureUserBelongsToAccount(int $accountId, int $userId): void
    {
        if (! $this->assignableUsersForAccount($accountId)->where('tbl_users.id', $userId)->exists()) {
            throw ValidationException::withMessages([
                'user_id' => 'The selected user is not available for this account.',
            ]);
        }
    }

    protected function ensureAwaitingService(Service $service): void
    {
        // Status transitions are explicit so only awaiting services can be
        // opened and the workflow cannot skip intermediate states.
        if (! $service->isAwaitingService()) {
            throw ValidationException::withMessages([
                'service' => 'Only services with Awaiting Service status can be opened.',
            ]);
        }
    }

    protected function ensureServiceOpen(Service $service): void
    {
        // Transaction writes are blocked unless the service is actively open.
        if (! $service->isServiceOpen()) {
            throw ValidationException::withMessages([
                'service' => 'Only services with Service Open status can be modified.',
            ]);
        }
    }

    protected function ensureLocationService(Service $service, string $message): void
    {
        if (! $service->isLocationService()) {
            throw ValidationException::withMessages([
                'service' => $message,
            ]);
        }
    }

    protected function ensureMaintenanceService(Service $service, string $message): void
    {
        if (! $service->isMaintenanceService()) {
            throw ValidationException::withMessages([
                'service' => $message,
            ]);
        }
    }

    protected function ensureSupportsInventoryTransactions(Service $service): void
    {
        if (! $service->supportsInventoryTransactions()) {
            throw ValidationException::withMessages([
                'service' => 'Inventory transactions are only available for location services.',
            ]);
        }
    }

    protected function validateBinQuantities(Request $request, Machine $machine, bool $enforceCapacity): array
    {
        if ($machine->bins->isEmpty()) {
            throw ValidationException::withMessages([
                'machine' => 'This machine does not have any bins to service.',
            ]);
        }

        $rules = [
            'quantities' => ['required', 'array'],
        ];

        $attributes = [];

        foreach ($machine->bins as $bin) {
            $binRules = ['required', 'integer', 'min:0'];

            if ($enforceCapacity && (int) $bin->capacity > 0) {
                $binRules[] = 'max:'.$bin->capacity;
            }

            $rules['quantities.'.$bin->id] = $binRules;
            $attributes['quantities.'.$bin->id] = $bin->bin_code.' quantity';
        }

        $validated = validator($request->all(), $rules, [], $attributes)->validate();

        return array_map('intval', $validated['quantities']);
    }

    protected function validateBinCounts(Request $request, Machine $machine): array
    {
        if ($machine->bins->isEmpty()) {
            throw ValidationException::withMessages([
                'machine' => 'This machine does not have any bins to service.',
            ]);
        }

        $rules = [
            'counts' => ['required', 'array'],
        ];

        $attributes = [];

        foreach ($machine->bins as $bin) {
            $quantityRules = ['required', 'integer', 'min:0'];

            if ((int) $bin->capacity > 0) {
                $quantityRules[] = 'max:'.$bin->capacity;
            }

            // Validate spoilage explicitly so unsellable units cannot be omitted or submitted as negative values.
            $rules['counts.'.$bin->id.'.quantity'] = $quantityRules;
            $rules['counts.'.$bin->id.'.spoilage'] = ['required', 'integer', 'min:0'];
            $attributes['counts.'.$bin->id.'.quantity'] = $bin->bin_code.' count quantity';
            $attributes['counts.'.$bin->id.'.spoilage'] = $bin->bin_code.' spoilage';
        }

        $validated = validator($request->all(), $rules, [], $attributes)->validate();

        return collect($validated['counts'])
            ->mapWithKeys(fn (array $count, string|int $binId) => [
                (int) $binId => [
                    'quantity' => (int) $count['quantity'],
                    'spoilage' => (int) $count['spoilage'],
                ],
            ])
            ->all();
    }

    protected function ensureAwaitingAmountCollected(Service $service): void
    {
        // Final collection entry is only valid after the technician has
        // completed the visit and before the service has been fully closed.
        if (! $service->isServiceCompleted() || $service->amount_collected !== null) {
            throw ValidationException::withMessages([
                'service' => 'Only completed services awaiting money entry can be closed.',
            ]);
        }
    }

    protected function groupTransactionsForService(Service $service): Collection
    {
        $typeOrder = [
            Transaction::TYPE_CURRENT_INVENTORY => 1,
            Transaction::TYPE_FILL => 1,
            Transaction::TYPE_COUNT => 2,
            Transaction::TYPE_ADD => 3,
            Transaction::TYPE_WASTE => 4,
            Transaction::TYPE_REMOVE => 5,
            Transaction::TYPE_ADJUSTMENT => 6,
        ];

        // Transaction history stays inside the selected account and service so
        // the detail page cannot leak rows from another tenant.
        $transactions = Transaction::query()
            ->where('account_id', $service->account_id)
            ->where('service_id', $service->id)
            ->with(['machine', 'bin', 'product'])
            ->orderByDesc('transaction_at')
            ->orderByDesc('id')
            ->get();

        return $transactions
            ->groupBy(fn (Transaction $transaction) => $transaction->transaction_at?->toDateString() ?? 'Unknown Date')
            ->map(function (Collection $transactionsForDate) use ($typeOrder) {
                return $transactionsForDate
                    ->groupBy('transaction_type')
                    ->sortBy(fn (Collection $transactions, string $type) => $typeOrder[$type] ?? 99);
            });
    }

    protected function groupSalesByMachine(Service $service): Collection
    {
        // Group eager-loaded sales lines once so each machine accordion can render without extra queries.
        return $service->sales
            ->groupBy(fn ($sale) => $sale->machine_id ?: 'unassigned')
            ->map(function (Collection $sales) {
                $machine = $sales->first()?->machine;
                $calculatedSales = $sales->filter(fn ($sale) => $sale->isCalculated());
                $baselineSales = $sales->filter(fn ($sale) => $sale->isBaseline());
                $totalSalesCents = $calculatedSales->reduce(
                    fn (int $carry, $sale) => $carry + ($sale->sales_amount !== null ? Money::toCents($sale->sales_amount) : 0),
                    0
                );

                return [
                    'machine' => $machine,
                    'sales' => $sales->values(),
                    'bin_count' => $sales->pluck('bin_id')->filter()->unique()->count(),
                    'calculated_count' => $calculatedSales->count(),
                    'baseline_count' => $baselineSales->count(),
                    'total_units_sold' => (int) $calculatedSales->sum(fn ($sale) => (int) ($sale->units_sold ?? 0)),
                    'total_sales' => $calculatedSales->isNotEmpty() ? Money::fromCents($totalSalesCents) : null,
                ];
            })
            ->sortBy(fn (array $group) => mb_strtolower((string) ($group['machine']?->serial_number ?: $group['machine']?->type ?: 'unknown machine')))
            ->values();
    }

    protected function groupServicesByLocation($services)
    {
        return $services
            ->groupBy(fn (Service $service) => $service->location_id ?? 'unknown')
            ->sortBy(function ($group) {
                $locationName = $group->first()?->location?->location_name ?? 'Unknown Location';

                return mb_strtolower($locationName);
            });
    }

    protected function serviceTypesForAccount(int $accountId): array
    {
        return $this->dataDictionaryService
            ->options(DataDictionary::GROUP_SERVICE_TYPE, $accountId)
            ->mapWithKeys(fn (DataDictionary $entry) => [$entry->value => $entry->displayLabel()])
            ->all();
    }
}
