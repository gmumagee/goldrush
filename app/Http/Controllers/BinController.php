<?php

namespace App\Http\Controllers;

use App\Models\Bin;
use App\Models\Machine;
use App\Models\Product;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BinController extends Controller
{
    public function index(Request $request, InventoryService $inventoryService): View
    {
        $this->authorize('viewAny', Bin::class);

        $accountId = $this->currentAccountId($request);
        $search = trim((string) $request->string('search'));
        $machineId = $request->integer('machine_id');

        $bins = Bin::query()
            ->where('account_id', $accountId)
            ->with([
                'machine.location',
                'product',
            ])
            ->when($machineId, fn ($query) => $query->where('machine_id', $machineId))
            ->when($search !== '', fn ($query) => $query->where('bin_code', 'like', '%'.$search.'%'))
            ->orderBy('bin_code')
            ->paginate(25)
            ->withQueryString();

        $inventoryByBin = $inventoryService->getCurrentInventoryForBins($bins->getCollection(), $accountId);

        $machines = Machine::query()
            ->where('account_id', $accountId)
            ->orderBy('type')
            ->orderBy('serial_number')
            ->get();

        return view('bins.index', [
            'bins' => $bins,
            'inventoryByBin' => $inventoryByBin,
            'machines' => $machines,
            'search' => $search,
            'selectedMachineId' => $machineId,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Bin::class);

        $accountId = $this->currentAccountId($request);

        return view('bins.create', [
            'machines' => $this->machinesForAccount($accountId),
            'products' => $this->productsForAccount($accountId),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Bin::class);

        $accountId = $this->currentAccountId($request);
        $data = $this->validateBin($request, $accountId);

        Bin::create($data + ['account_id' => $accountId]);

        return redirect()
            ->route('bins.index')
            ->with('status', 'Bin created successfully.');
    }

    public function show(Request $request, int $bin, InventoryService $inventoryService): View
    {
        $accountId = $this->currentAccountId($request);
        $bin = $this->binForAccount($accountId, $bin, ['machine.location', 'product']);
        $this->authorize('view', $bin);

        return view('bins.show', [
            'bin' => $bin,
            'currentInventory' => $inventoryService->getCurrentInventoryForBin($bin),
        ]);
    }

    public function edit(Request $request, int $bin): View
    {
        $accountId = $this->currentAccountId($request);
        $bin = $this->binForAccount($accountId, $bin, ['machine.location', 'product']);
        $this->authorize('update', $bin);

        return view('bins.edit', [
            'bin' => $bin,
            'machines' => $this->machinesForAccount($accountId),
            'products' => $this->productsForAccount($accountId),
        ]);
    }

    public function update(Request $request, int $bin): RedirectResponse
    {
        $accountId = $this->currentAccountId($request);
        $bin = $this->binForAccount($accountId, $bin);
        $this->authorize('update', $bin);
        $data = $this->validateBin($request, $accountId, $bin);

        $bin->update($data);

        return redirect()
            ->route('bins.show', $bin->id)
            ->with('status', 'Bin updated successfully.');
    }

    public function destroy(Request $request, int $bin): RedirectResponse
    {
        $accountId = $this->currentAccountId($request);
        $bin = $this->binForAccount($accountId, $bin, ['transactions']);
        $this->authorize('delete', $bin);

        if ($bin->transactions()->exists()) {
            return back()->withErrors([
                'bin' => 'Bin cannot be deleted because it has transactions.',
            ]);
        }

        $bin->delete();

        return redirect()
            ->route('bins.index')
            ->with('status', 'Bin deleted successfully.');
    }

    protected function validateBin(Request $request, int $accountId, ?Bin $bin = null): array
    {
        $machineId = $request->integer('machine_id');

        return $request->validate([
            'machine_id' => [
                'required',
                'integer',
                Rule::exists('tbl_machines', 'id')->where(fn ($query) => $query->where('account_id', $accountId)),
            ],
            'product_id' => [
                'nullable',
                'integer',
                Rule::exists('tbl_products', 'id')->where(fn ($query) => $query->where('account_id', $accountId)),
            ],
            'bin_code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('tbl_bins', 'bin_code')
                    ->where(fn ($query) => $query->where('machine_id', $machineId))
                    ->ignore($bin?->id),
            ],
            'capacity' => ['required', 'integer', 'min:0'],
            'price' => ['nullable', 'numeric', 'min:0'],
        ]);
    }

    protected function binForAccount(int $accountId, int $binId, array $with = []): Bin
    {
        // Account isolation: every bin CRUD request is scoped to the selected
        // account so cross-account machine and product data cannot leak.
        return Bin::query()
            ->where('account_id', $accountId)
            ->with($with)
            ->findOrFail($binId);
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

    protected function productsForAccount(int $accountId)
    {
        return Product::query()
            ->where('account_id', $accountId)
            ->orderedForDropdown()
            ->get();
    }
}
