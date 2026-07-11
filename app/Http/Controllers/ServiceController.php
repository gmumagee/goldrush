<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Machine;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function index(Request $request): View
    {
        $accountId = $this->currentAccountId($request);
        $status = $request->string('status')->toString();
        $locationId = $request->integer('location_id');
        $serviceDate = $request->input('service_date');

        $services = Service::query()
            ->where('account_id', $accountId)
            ->with(['location.route', 'user'])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($locationId, fn ($query) => $query->where('location_id', $locationId))
            ->when($serviceDate, fn ($query) => $query->whereDate('service_date', $serviceDate))
            ->orderByDesc('service_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('services.index', [
            'services' => $services,
            'locations' => $this->locationsForAccount($accountId),
            'statuses' => $this->serviceStatuses(),
            'filters' => [
                'status' => $status,
                'location_id' => $locationId,
                'service_date' => $serviceDate,
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $accountId = $this->currentAccountId($request);

        return view('services.create', [
            'locations' => $this->locationsForAccount($accountId),
            'users' => $this->assignableUsersForAccount($accountId)->get(),
            'currentUser' => $request->user(),
            'serviceStatuses' => $this->serviceStatuses(),
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

        $service = Service::create([
            'account_id' => $accountId,
            'location_id' => (int) $data['location_id'],
            'user_id' => $assignedUserId,
            'service_type' => $data['service_type'] ?? Service::TYPE_LOCATION_SERVICE,
            'service_date' => $data['service_date'],
            'opened_at' => null,
            'closed_at' => null,
            'status' => Service::STATUS_AWAITING_SERVICE,
        ]);

        return redirect()
            ->route('services.show', $service->id)
            ->with('status', 'Service created successfully.');
    }

    public function show(Request $request, int $service): View
    {
        $service = $this->resolveService($request, $service, [
            'location.route',
            'location.machines' => fn ($query) => $query->orderBy('type')->orderBy('id'),
            'location.machines.bins',
            'user',
            'transactions' => fn ($query) => $query->latest('id'),
            'transactions.bin.machine',
            'transactions.machine',
            'transactions.product',
        ]);

        return view('services.show', [
            'service' => $service,
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
            'users' => $this->assignableUsersForAccount($service->account_id)->get(),
            'serviceStatuses' => $this->serviceStatuses(),
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

        $service->update([
            'location_id' => (int) $data['location_id'],
            'user_id' => $assignedUserId,
            'service_type' => $data['service_type'] ?? $service->service_type,
            'service_date' => $data['service_date'],
        ]);

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

        $service->delete();

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
        ]);

        return redirect()
            ->route('services.show', $service->id)
            ->with('status', 'Service opened.');
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

    public function storeCount(Request $request, int $service, int $machine): RedirectResponse
    {
        $service = $this->resolveService($request, $service);
        $this->ensureServiceOpen($service);

        $machine = $this->resolveMachineForService($service, $machine, [
            'bins' => fn ($query) => $query->with('product')->orderBy('bin_code'),
        ]);

        $quantities = $this->validateBinQuantities($request, $machine, true);

        DB::transaction(function () use ($service, $machine, $quantities) {
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
                    'unit_cost' => null,
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

    public function storeFill(Request $request, int $service, int $machine): RedirectResponse
    {
        $service = $this->resolveService($request, $service);
        $this->ensureServiceOpen($service);

        $machine = $this->resolveMachineForService($service, $machine, [
            'bins' => fn ($query) => $query->with('product')->orderBy('bin_code'),
        ]);

        $quantities = $this->validateBinQuantities($request, $machine, false);

        DB::transaction(function () use ($service, $machine, $quantities) {
            foreach ($machine->bins as $bin) {
                Transaction::create([
                    'account_id' => $service->account_id,
                    'service_id' => $service->id,
                    // Write machine_id from the persisted bin so transaction
                    // and bin cannot drift based on client input.
                    'machine_id' => $bin->machine_id,
                    'bin_id' => $bin->id,
                    'product_id' => $bin->product_id,
                    'transaction_type' => 'fill',
                    'quantity' => (int) $quantities[$bin->id],
                    'transaction_at' => now(),
                    'price' => $bin->price,
                    'unit_cost' => null,
                ]);
            }
        });

        return redirect()
            ->route('services.show', $service->id)
            ->with('status', 'Machine fill recorded successfully.');
    }

    public function close(Request $request, int $service): RedirectResponse
    {
        $service = $this->resolveService($request, $service);
        $this->ensureServiceOpen($service);

        $service->update([
            'status' => Service::STATUS_SERVICE_CLOSED,
            'closed_at' => now(),
        ]);

        return redirect()
            ->route('services.show', $service->id)
            ->with('status', 'Service closed.');
    }

    protected function validateService(Request $request, int $accountId, bool $creating = true): array
    {
        return $request->validate([
            'location_id' => [
                'required',
                'integer',
                Rule::exists('tbl_locations', 'id')->where(fn ($query) => $query->where('account_id', $accountId)),
            ],
            'service_date' => ['required', 'date'],
            'service_type' => ['nullable', 'string', 'max:50'],
            'user_id' => ['nullable', 'integer'],
            'status' => [$creating ? 'nullable' : 'sometimes', 'string'],
        ]);
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

    protected function assignableUsersForAccount(int $accountId)
    {
        return User::query()
            ->select('tbl_users.*')
            ->join('tbl_account_users', 'tbl_account_users.user_id', '=', 'tbl_users.id')
            ->where('tbl_account_users.account_id', $accountId)
            ->where('tbl_account_users.status', 'active')
            ->where('tbl_users.status', 'active')
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

    protected function serviceStatuses(): array
    {
        return [
            Service::STATUS_AWAITING_SERVICE,
            Service::STATUS_SERVICE_OPEN,
            Service::STATUS_SERVICE_CLOSED,
        ];
    }
}
