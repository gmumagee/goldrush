<?php

namespace App\Http\Controllers;

use App\Models\Bin;
use App\Models\Location;
use App\Models\Machine;
use App\Models\Service;
use App\Models\Transaction;
use App\Services\InventoryCostService;
use App\Services\WarehouseInventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TransactionController extends Controller
{
    // Limit manual machine-bin transactions to the supported service workflow types.
    protected const TYPES = ['count', 'fill', 'waste', 'remove', 'adjustment'];

    public function index(Request $request): View
    {
        $accountId = $this->currentAccountId($request);
        $serviceId = $request->integer('service_id');
        $machineId = $request->integer('machine_id');
        $transactionType = $request->string('transaction_type')->toString();
        $filters = $request->validate([
            'date_from' => ['nullable', 'regex:/^\d{2}-\d{2}-\d{4}$/'],
            'date_to' => ['nullable', 'regex:/^\d{2}-\d{2}-\d{4}$/'],
        ]);
        $dateFrom = $this->normalizeDateInput($filters['date_from'] ?? null, 'date_from', true);
        $dateTo = $this->normalizeDateInput($filters['date_to'] ?? null, 'date_to', true);

        $transactions = Transaction::query()
            ->where('account_id', $accountId)
            ->with(['service.location', 'machine', 'bin', 'product'])
            ->when($serviceId, fn ($query) => $query->where('service_id', $serviceId))
            ->when($machineId, fn ($query) => $query->where('machine_id', $machineId))
            ->when($transactionType !== '', fn ($query) => $query->where('transaction_type', $transactionType))
            ->when($dateFrom, fn ($query) => $query->whereDate('transaction_at', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('transaction_at', '<=', $dateTo))
            ->orderByDesc('transaction_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('transactions.index', [
            'transactions' => $transactions,
            'services' => $this->servicesForAccount($accountId),
            'machines' => $this->machinesForAccount($accountId),
            'transactionTypes' => self::TYPES,
            'filters' => [
                'service_id' => $serviceId,
                'machine_id' => $machineId,
                'transaction_type' => $transactionType,
                'date_from' => $filters['date_from'] ?? null,
                'date_to' => $filters['date_to'] ?? null,
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $accountId = $this->currentAccountId($request);
        $selectedMachineId = $request->integer('machine_id');
        $selectedBinId = $request->integer('bin_id');

        return view('transactions.create', [
            'services' => $this->servicesForAccount($accountId),
            'machines' => $this->machinesForAccount($accountId),
            'bins' => $this->binsForAccount($accountId, $selectedMachineId),
            'transactionTypes' => self::TYPES,
            'selectedBin' => $selectedBinId ? $this->binForAccount($accountId, $selectedBinId, ['product', 'machine']) : null,
        ]);
    }

    public function store(
        Request $request,
        WarehouseInventoryService $warehouseInventoryService,
        InventoryCostService $inventoryCostService,
    ): RedirectResponse
    {
        $accountId = $this->currentAccountId($request);
        [$service, $bin, $payload] = $this->validatedTransactionPayload($request, $accountId);

        if ($payload['transaction_type'] === 'fill') {
            $warehouseInventoryService->createFillTransaction($service, [[
                'machine_id' => $bin->machine_id,
                'bin_id' => $bin->id,
                'bin_code' => $bin->bin_code,
                'product_id' => $bin->product_id,
                'product_name' => $bin->product?->product_name,
                'quantity' => (int) $payload['quantity'],
                'price' => $payload['price'] ?? $bin->price,
                'transaction_at' => $payload['transaction_at'],
            ]]);
        } else {
            Transaction::create([
                'account_id' => $accountId,
                'service_id' => $service->id,
                // Machine isolation: writes always derive the machine from the
                // persisted bin instead of trusting request input.
                'machine_id' => $bin->machine_id,
                'bin_id' => $bin->id,
                'product_id' => $bin->product_id,
                'transaction_type' => $payload['transaction_type'],
                'quantity' => (int) $payload['quantity'],
                'spoilage' => $payload['transaction_type'] === Transaction::TYPE_COUNT
                    ? (int) $payload['spoilage']
                    : 0,
                'price' => $payload['price'] ?? $bin->price,
                'unit_cost' => $payload['transaction_type'] === 'count'
                    ? $inventoryCostService->getUnitCostForCount(
                        $accountId,
                        $service->warehouse_id ? (int) $service->warehouse_id : null,
                        $bin->id,
                        $bin->product_id ? (int) $bin->product_id : null,
                    )
                    : null,
                'transaction_at' => $payload['transaction_at'],
            ]);
        }

        return redirect()
            ->route('transactions.index')
            ->with('status', 'Transaction created successfully.');
    }

    public function show(Request $request, int $transaction): View
    {
        $transaction = $this->transactionForAccount($this->currentAccountId($request), $transaction, [
            'service.location',
            'machine.location',
            'bin',
            'product',
        ]);

        return view('transactions.show', [
            'transaction' => $transaction,
        ]);
    }

    public function edit(Request $request, int $transaction): View|RedirectResponse
    {
        $accountId = $this->currentAccountId($request);
        $transaction = $this->transactionForAccount($accountId, $transaction, [
            'service.location',
            'machine',
            'bin.product',
            'product',
        ]);

        if (! $transaction->service?->isServiceOpen()) {
            return redirect()
                ->route('transactions.show', $transaction->id)
                ->withErrors(['transaction' => 'Only Service Open transactions can be edited.']);
        }

        if (! $transaction->service?->isLocationService()) {
            return redirect()
                ->route('transactions.show', $transaction->id)
                ->withErrors(['transaction' => 'Inventory transactions are only available for location services.']);
        }

        if ($transaction->transaction_type === 'fill') {
            return redirect()
                ->route('transactions.show', $transaction->id)
                ->withErrors(['transaction' => 'Fill transactions cannot be edited because they are tied to warehouse inventory ledger history.']);
        }

        return view('transactions.edit', [
            'transaction' => $transaction,
            'services' => $this->servicesForAccount($accountId),
            'machines' => $this->machinesForAccount($accountId),
            'bins' => $this->binsForAccount($accountId, $transaction->machine_id),
            'transactionTypes' => self::TYPES,
        ]);
    }

    public function update(Request $request, int $transaction, InventoryCostService $inventoryCostService): RedirectResponse
    {
        $accountId = $this->currentAccountId($request);
        $transaction = $this->transactionForAccount($accountId, $transaction, ['service']);

        if (! $transaction->service?->isServiceOpen()) {
            return back()->withErrors(['transaction' => 'Only Service Open transactions can be edited.']);
        }

        if (! $transaction->service?->isLocationService()) {
            return back()->withErrors(['transaction' => 'Inventory transactions are only available for location services.']);
        }

        if ($transaction->transaction_type === 'fill') {
            return back()->withErrors(['transaction' => 'Fill transactions cannot be edited because they are tied to warehouse inventory ledger history.']);
        }

        [$service, $bin, $payload] = $this->validatedTransactionPayload($request, $accountId);

        if ($payload['transaction_type'] === 'fill') {
            return back()->withErrors(['transaction' => 'Fill transactions cannot be edited or converted because they are tied to warehouse inventory ledger history.']);
        }

        $transaction->update([
            'service_id' => $service->id,
            'machine_id' => $bin->machine_id,
            'bin_id' => $bin->id,
            'product_id' => $bin->product_id,
            'transaction_type' => $payload['transaction_type'],
            'quantity' => (int) $payload['quantity'],
            'spoilage' => $payload['transaction_type'] === Transaction::TYPE_COUNT
                ? (int) $payload['spoilage']
                : 0,
            'price' => $payload['price'] ?? $bin->price,
            'unit_cost' => $payload['transaction_type'] === 'count'
                ? $inventoryCostService->getUnitCostForCount(
                    $accountId,
                    $service->warehouse_id ? (int) $service->warehouse_id : null,
                    $bin->id,
                    $bin->product_id ? (int) $bin->product_id : null,
                )
                : null,
            'transaction_at' => $payload['transaction_at'],
        ]);

        return redirect()
            ->route('transactions.show', $transaction->id)
            ->with('status', 'Transaction updated successfully.');
    }

    public function destroy(Request $request, int $transaction): RedirectResponse
    {
        $transaction = $this->transactionForAccount($this->currentAccountId($request), $transaction, ['service']);

        if (! $transaction->service?->isServiceOpen()) {
            return back()->withErrors(['transaction' => 'Only Service Open transactions can be deleted.']);
        }

        if (! $transaction->service?->isLocationService()) {
            return back()->withErrors(['transaction' => 'Inventory transactions are only available for location services.']);
        }

        if ($transaction->transaction_type === 'fill') {
            return back()->withErrors(['transaction' => 'Fill transactions cannot be deleted because they are tied to warehouse inventory ledger history.']);
        }

        $transaction->delete();

        return redirect()
            ->route('transactions.index')
            ->with('status', 'Transaction deleted successfully.');
    }

    protected function validatedTransactionPayload(Request $request, int $accountId): array
    {
        $payload = $request->validate([
            'service_id' => [
                'required',
                'integer',
                Rule::exists('tbl_services', 'id')->where(fn ($query) => $query->where('account_id', $accountId)),
            ],
            'machine_id' => [
                'required',
                'integer',
                Rule::exists('tbl_machines', 'id')->where(fn ($query) => $query->where('account_id', $accountId)),
            ],
            'bin_id' => [
                'required',
                'integer',
                Rule::exists('tbl_bins', 'id')->where(fn ($query) => $query->where('account_id', $accountId)),
            ],
            'transaction_type' => ['required', Rule::in(self::TYPES)],
            'quantity' => ['required', 'integer'],
            'spoilage' => [
                Rule::requiredIf(fn () => $request->input('transaction_type') === Transaction::TYPE_COUNT),
                'nullable',
                'integer',
                'min:0',
            ],
            'price' => ['nullable', 'numeric', 'min:0'],
            'transaction_date' => ['required', 'regex:/^\d{2}-\d{2}-\d{4}$/'],
            'transaction_time' => ['required', 'regex:/^\d{2}:\d{2}:\d{2}$/'],
        ]);

        // Normalize spoilage once so count edits keep their value while other transaction types stay at zero.
        $payload['spoilage'] = $payload['transaction_type'] === Transaction::TYPE_COUNT
            ? (int) ($payload['spoilage'] ?? 0)
            : 0;

        $payload['transaction_at'] = $this->combineDateAndTimeInputs(
            $payload['transaction_date'] ?? null,
            $payload['transaction_time'] ?? null,
            'transaction_date',
            'transaction_time',
        );

        $service = Service::query()
            ->where('account_id', $accountId)
            ->findOrFail((int) $payload['service_id']);

        $bin = $this->binForAccount($accountId, (int) $payload['bin_id'], ['machine', 'product']);

        if (! $service->isServiceOpen()) {
            throw ValidationException::withMessages([
                'service_id' => 'Transactions can only be added while the service is Service Open.',
            ]);
        }

        if (! $service->isLocationService()) {
            throw ValidationException::withMessages([
                'service_id' => 'Inventory transactions are only available for location services.',
            ]);
        }

        if ($payload['transaction_type'] === 'fill' && ! $service->warehouse_id) {
            throw ValidationException::withMessages([
                'service_id' => 'The selected service does not have a source warehouse.',
            ]);
        }

        if ((int) $payload['machine_id'] !== (int) $bin->machine_id) {
            throw ValidationException::withMessages([
                'machine_id' => 'The selected machine must match the selected bin.',
            ]);
        }

        // Location isolation: manual transaction entry cannot bind a bin from
        // a different machine or location than the selected service.
        if ((int) $bin->machine?->location_id !== (int) $service->location_id) {
            throw ValidationException::withMessages([
                'bin_id' => 'The selected bin is not on a machine at the service location.',
            ]);
        }

        return [$service, $bin, $payload];
    }

    protected function transactionForAccount(int $accountId, int $transactionId, array $with = []): Transaction
    {
        // Account isolation: every transaction lookup stays inside the current
        // account before related service, machine, bin, and product data load.
        return Transaction::query()
            ->where('account_id', $accountId)
            ->with($with)
            ->findOrFail($transactionId);
    }

    protected function servicesForAccount(int $accountId)
    {
        return Service::query()
            ->where('account_id', $accountId)
            ->with('location')
            ->orderByDesc('service_date')
            ->orderByDesc('id')
            ->get();
    }

    protected function machinesForAccount(int $accountId)
    {
        return Machine::query()
            ->where('account_id', $accountId)
            ->with('location')
            ->orderBy('type')
            ->orderBy('serial_number')
            ->get();
    }

    protected function binsForAccount(int $accountId, ?int $machineId = null)
    {
        return Bin::query()
            ->where('account_id', $accountId)
            ->with(['machine', 'product'])
            ->when($machineId, fn ($query) => $query->where('machine_id', $machineId))
            ->orderBy('bin_code')
            ->get();
    }

    protected function binForAccount(int $accountId, int $binId, array $with = []): Bin
    {
        return Bin::query()
            ->where('account_id', $accountId)
            ->with($with)
            ->findOrFail($binId);
    }
}
