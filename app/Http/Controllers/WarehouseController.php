<?php

namespace App\Http\Controllers;

use App\Models\DataDictionary;
use App\Models\Warehouse;
use App\Services\DataDictionaryService;
use App\Services\WarehouseInventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WarehouseController extends Controller
{
    public function __construct(
        protected WarehouseInventoryService $warehouseInventoryService,
        protected DataDictionaryService $dataDictionaryService,
    )
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Warehouse::class);

        $accountId = $this->currentAccountId($request);
        $search = trim((string) $request->string('search'));

        $warehouses = Warehouse::query()
            ->where('account_id', $accountId)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($warehouseQuery) use ($search) {
                    $warehouseQuery
                        ->where('warehouse_name', 'like', '%'.$search.'%')
                        ->orWhere('city', 'like', '%'.$search.'%')
                        ->orWhere('state', 'like', '%'.$search.'%')
                        ->orWhere('zip_code', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('id', 'desc')
            ->paginate(25)
            ->withQueryString();

        return view('warehouses.index', compact('warehouses', 'search'));
    }

    public function create(): View
    {
        $this->authorize('create', Warehouse::class);

        return view('warehouses.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Warehouse::class);

        $accountId = $this->currentAccountId($request);

        $data = $request->validate([
            'warehouse_name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'zip_code' => ['nullable', 'string', 'max:20'],
        ]);

        $data['account_id'] = $accountId;

        Warehouse::create($data);

        return redirect()->route('warehouses.index')->with('status', 'Warehouse created successfully.');
    }

    public function show(Request $request, int $warehouse): View
    {
        $accountId = $this->currentAccountId($request);
        $search = trim((string) $request->string('search'));
        $warehouse = $this->warehouseForAccount($accountId, $warehouse, ['purchases.vendor']);
        $this->authorize('view', $warehouse);

        return view('warehouses.show', [
            'warehouse' => $warehouse,
            'search' => $search,
            'inventoryRows' => $this->warehouseInventoryService->inventoryForWarehouse($accountId, $warehouse->id, $search),
            'recentLedger' => $this->warehouseInventoryService->ledgerForWarehouse($accountId, $warehouse->id),
            'movementTypeLabels' => $this->dataDictionaryService->labels(DataDictionary::GROUP_INVENTORY_MOVEMENT_TYPE, $accountId, true),
        ]);
    }

    public function edit(Request $request, int $warehouse): View
    {
        $warehouse = $this->warehouseForAccount($this->currentAccountId($request), $warehouse);
        $this->authorize('update', $warehouse);

        return view('warehouses.edit', compact('warehouse'));
    }

    public function update(Request $request, int $warehouse): RedirectResponse
    {
        $warehouse = $this->warehouseForAccount($this->currentAccountId($request), $warehouse);
        $this->authorize('update', $warehouse);

        $data = $request->validate([
            'warehouse_name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'zip_code' => ['nullable', 'string', 'max:20'],
        ]);

        $warehouse->update($data);

        return redirect()->route('warehouses.show', $warehouse)->with('status', 'Warehouse updated successfully.');
    }

    public function destroy(Request $request, int $warehouse): RedirectResponse
    {
        $warehouse = $this->warehouseForAccount($this->currentAccountId($request), $warehouse, [
            'purchases',
            'services',
            'inventoryLedger',
        ]);
        $this->authorize('delete', $warehouse);

        if ($warehouse->purchases()->exists() || $warehouse->services()->exists() || $warehouse->inventoryLedger()->exists()) {
            return back()->withErrors([
                'warehouse' => 'Warehouse cannot be deleted because it is used by purchases, services, or inventory ledger rows.',
            ]);
        }

        $warehouse->delete();

        return redirect()->route('warehouses.index')->with('status', 'Warehouse deleted successfully.');
    }

    protected function warehouseForAccount(int $accountId, int $warehouseId, array $with = []): Warehouse
    {
        return Warehouse::query()
            ->where('account_id', $accountId)
            ->with($with)
            ->findOrFail($warehouseId);
    }
}
