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
use App\Services\InventoryCostService;
use App\Services\WarehouseInventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function __construct(
        protected DataDictionaryService $dataDictionaryService,
        protected CalendarService $calendarService,
    )
    {
    }

    public function index(Request $request): View
    {
        $accountId = $this->currentAccountId($request);

        $pendingServices = Service::query()
            ->where('account_id', $accountId)
            ->with(['location.route', 'warehouse', 'user', 'closedBy'])
            ->whereIn('status', [Service::STATUS_AWAITING_SERVICE, 'awaiting service'])
            ->orderBy('service_date')
            ->orderBy('id')
            ->get();

        $completedServicesAwaitingMoney = Service::query()
            ->where('account_id', $accountId)
            ->with(['location.route', 'warehouse', 'user', 'closedBy'])
            ->where('status', Service::STATUS_SERVICE_COMPLETED)
            ->whereNull('amount_collected')
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->get();

        $allServices = Service::query()
            ->where('account_id', $accountId)
            ->with(['location.route', 'warehouse', 'user', 'closedBy'])
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
        ]);
    }

    public function create(Request $request): View
    {
        $accountId = $this->currentAccountId($request);

        return view('services.create', [
            'locations' => $this->locationsForAccount($accountId),
            'warehouses' => $this->warehousesForAccount($accountId),
            'users' => $this->assignableUsersForAccount($accountId)->get(),
            'currentUser' => $request->user(),
            'serviceStatusLabels' => $this->dataDictionaryService->labels(DataDictionary::GROUP_SERVICE_STATUS, $accountId, true),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $accountId = $this->currentAccountId($request);
        $data = $this->validateService($request, $accountId);

        $assignedUserId = isset($data['user_id']) && $data['user_id'] !== null
            ? (int) $data['user_id']
            : (int) $request->user()->id;

        $this->ensureUserBelongsToAccount($accountId, $assignedUserId);

        $service = DB::transaction(function () use ($accountId, $data, $assignedUserId, $request) {
            $service = Service::create([
                'account_id' => $accountId,
                'location_id' => (int) $data['location_id'],
                'warehouse_id' => (int) $data['warehouse_id'],
                'user_id' => $assignedUserId,
                'closed_by_user_id' => null,
                'service_type' => $data['service_type'] ?? Service::TYPE_LOCATION_SERVICE,
                'service_date' => $data['service_date'],
                'scheduled_at' => $data['scheduled_at'],
                'opened_at' => null,
                'completed_at' => null,
                'closed_at' => null,
                'amount_collected' => null,
                'status' => Service::STATUS_AWAITING_SERVICE,
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
        $service = $this->resolveService($request, $service, [
            'location.route',
            'warehouse',
            'location.machines' => fn ($query) => $query->orderBy('type')->orderBy('id'),
            'location.machines.bins',
            'user',
            'closedBy',
            'calendarEvents',
        ]);

        $transactionsByDateAndType = $this->groupTransactionsForService($service);

        return view('services.show', [
            'service' => $service,
            'transactionsByDateAndType' => $transactionsByDateAndType,
            'serviceStatusLabels' => $this->dataDictionaryService->labels(DataDictionary::GROUP_SERVICE_STATUS, $service->account_id, true),
            'serviceCalendarEvent' => $service->calendarEvents->first(),
        ]);
    }

    public function edit(Request $request, int $service): View|RedirectResponse
    {
        $service = $this->resolveService($request, $service, ['location', 'user']);

        if ($service->isServiceClosed()) {
            return redirect()
                ->route('services.show', $service)
                ->withErrors(['service' => 'Closed services are read-only.']);
        }

        return view('services.edit', [
            'service' => $service,
            'locations' => $this->locationsForAccount($service->account_id),
            'warehouses' => $this->warehousesForAccount($service->account_id),
            'users' => $this->assignableUsersForAccount($service->account_id)->get(),
            'serviceStatusLabels' => $this->dataDictionaryService->labels(DataDictionary::GROUP_SERVICE_STATUS, $service->account_id, true),
        ]);
    }

    public function update(Request $request, int $service): RedirectResponse
    {
        $service = $this->resolveService($request, $service);

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

        DB::transaction(function () use ($service, $data, $assignedUserId) {
            $service->update([
                'location_id' => (int) $data['location_id'],
                'warehouse_id' => (int) $data['warehouse_id'],
                'user_id' => $assignedUserId,
                'service_type' => $data['service_type'] ?? $service->service_type,
                'service_date' => $data['service_date'],
                'scheduled_at' => $data['scheduled_at'],
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
        $this->ensureAwaitingService($service);

        $service->update([
            'status' => Service::STATUS_SERVICE_OPEN,
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

    public function complete(Request $request, int $service): RedirectResponse
    {
        $service = $this->resolveService($request, $service);
        $this->ensureServiceOpen($service);

        $service->update([
            'status' => Service::STATUS_SERVICE_COMPLETED,
            'completed_at' => now(),
            'closed_at' => null,
            'closed_by_user_id' => null,
            'amount_collected' => null,
        ]);

        $this->calendarService->updateServiceEvent($service->refresh());

        return redirect()
            ->route('services.show', $service->id)
            ->with('status', 'Service completed.');
    }

    public function editAmountCollected(Request $request, int $service): View
    {
        $service = $this->resolveService($request, $service, ['location.route', 'user', 'closedBy']);
        $this->ensureAwaitingAmountCollected($service);

        return view('services.amount-collected', [
            'service' => $service,
        ]);
    }

    public function updateAmountCollected(Request $request, int $service): RedirectResponse
    {
        $service = $this->resolveService($request, $service);
        $this->ensureAwaitingAmountCollected($service);
        $data = $request->validate([
            'amount_collected' => ['required', 'numeric', 'min:0'],
        ]);

        $service->update([
            'status' => Service::STATUS_SERVICE_CLOSED,
            'closed_at' => now(),
            'closed_by_user_id' => (int) $request->user()->id,
            'amount_collected' => $data['amount_collected'],
        ]);

        $this->calendarService->updateServiceEvent($service->refresh());

        return redirect()
            ->route('services.show', $service->id)
            ->with('status', 'Amount collected recorded and service closed.');
    }

    public function countMachine(Request $request, int $service, int $machine): View
    {
        $service = $this->resolveService($request, $service, ['location', 'user']);
        $this->ensureServiceOpen($service);

        $machine = $this->resolveMachineForService($service, $machine, [
            'location',
            'bins' => fn ($query) => $query->with('product')->orderBy('bin_code'),
        ]);

        return view('services.count-machine', [
            'service' => $service,
            'machine' => $machine,
        ]);
    }

    public function storeCount(Request $request, int $service, int $machine, InventoryCostService $inventoryCostService): RedirectResponse
    {
        $service = $this->resolveService($request, $service);
        $this->ensureServiceOpen($service);

        $machine = $this->resolveMachineForService($service, $machine, [
            'bins' => fn ($query) => $query->with('product')->orderBy('bin_code'),
        ]);

        $quantities = $this->validateBinQuantities($request, $machine, true);

        DB::transaction(function () use ($service, $machine, $quantities, $inventoryCostService) {
            foreach ($machine->bins as $bin) {
                Transaction::create([
                    'account_id' => $service->account_id,
                    'service_id' => $service->id,
                    // Write machine_id from the persisted bin so transaction
                    // and bin cannot drift based on client input.
                    'machine_id' => $bin->machine_id,
                    'bin_id' => $bin->id,
                    'product_id' => $bin->product_id,
                    'transaction_type' => 'count',
                    'quantity' => (int) $quantities[$bin->id],
                    'transaction_at' => now(),
                    'price' => $bin->price,
                    'unit_cost' => $inventoryCostService->getUnitCostForCount(
                        $service->account_id,
                        $service->warehouse_id ? (int) $service->warehouse_id : null,
                        $bin->id,
                        $bin->product_id ? (int) $bin->product_id : null,
                    ),
                ]);
            }
        });

        return redirect()
            ->route('services.show', $service->id)
            ->with('status', 'Machine count recorded successfully.');
    }

    public function fillMachine(Request $request, int $service, int $machine): View
    {
        $service = $this->resolveService($request, $service, ['location', 'user']);
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
        $data = $request->validate([
            'location_id' => [
                'required',
                'integer',
                Rule::exists('tbl_locations', 'id')->where(fn ($query) => $query->where('account_id', $accountId)),
            ],
            'warehouse_id' => [
                'required',
                'integer',
                Rule::exists('tbl_warehouses', 'id')->where(fn ($query) => $query->where('account_id', $accountId)),
            ],
            'service_date' => ['required', 'regex:/^\d{2}-\d{2}-\d{4}$/'],
            'scheduled_time' => ['nullable', 'regex:/^\d{2}:\d{2}:\d{2}$/'],
            'service_type' => ['nullable', 'string', 'max:50'],
            'user_id' => ['nullable', 'integer'],
            'status' => [
                $creating ? 'nullable' : 'sometimes',
                'string',
                $this->activeDictionaryValueRule(DataDictionary::GROUP_SERVICE_STATUS, $accountId),
            ],
        ]);

        $data['service_date'] = $this->normalizeDateInput($data['service_date'] ?? null, 'service_date');
        $data['scheduled_at'] = $this->combineDateAndTimeInputs(
            $request->input('service_date'),
            $request->input('scheduled_time', '09:00:00') ?: '09:00:00',
            'service_date',
            'scheduled_time',
        );

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
            ->with('route')
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
            'fill' => 1,
            'count' => 2,
            'add' => 3,
            'waste' => 4,
            'remove' => 5,
            'adjustment' => 6,
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

    protected function groupServicesByLocation($services)
    {
        return $services
            ->groupBy(fn (Service $service) => $service->location_id ?? 'unknown')
            ->sortBy(function ($group) {
                $locationName = $group->first()?->location?->location_name ?? 'Unknown Location';

                return mb_strtolower($locationName);
            });
    }
}
