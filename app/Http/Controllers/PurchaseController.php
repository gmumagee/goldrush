<?php

namespace App\Http\Controllers;

use App\Models\DataDictionary;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Services\DataDictionaryService;
use App\Services\WarehouseInventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PurchaseController extends Controller
{
    public function __construct(
        protected DataDictionaryService $dataDictionaryService,
        protected WarehouseInventoryService $warehouseInventoryService,
    ) {
    }

    public function index(Request $request): View
    {
        $accountId = $this->currentAccountId($request);

        $purchases = Purchase::query()
            ->where('account_id', $accountId)
            ->with(['vendor', 'warehouse'])
            ->withSum('items as total_amount', 'line_total')
            ->orderByDesc('purchase_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('purchases.index', [
            'purchases' => $purchases,
            'purchaseStatusLabels' => $this->dataDictionaryService->labels(DataDictionary::GROUP_PURCHASE_STATUS, $accountId, true),
        ]);
    }

    public function create(Request $request): View
    {
        $accountId = $this->currentAccountId($request);

        return view('purchases.create', [
            'vendors' => $this->vendorsForAccount($accountId),
            'warehouses' => $this->warehousesForAccount($accountId),
            'products' => $this->productsForAccount($accountId),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $accountId = $this->currentAccountId($request);
        $data = $this->validatePurchase($request, $accountId);

        $purchase = $this->warehouseInventoryService->postPurchase([
            'account_id' => $accountId,
            'vendor_id' => $data['vendor_id'] ?? null,
            'warehouse_id' => (int) $data['warehouse_id'],
            'invoice_number' => $data['invoice_number'] ?? null,
            'purchase_date' => $data['purchase_date'],
            'status' => Purchase::STATUS_POSTED,
            'notes' => $data['notes'] ?? null,
        ], $data['items']);

        return redirect()
            ->route('purchases.show', $purchase)
            ->with('status', 'Purchase posted successfully.');
    }

    public function show(Request $request, int $purchase): View
    {
        $purchase = $this->purchaseForAccount($this->currentAccountId($request), $purchase, [
            'items.product',
            'vendor',
            'warehouse',
        ]);

        return view('purchases.show', [
            'purchase' => $purchase,
            'purchaseStatusLabels' => $this->dataDictionaryService->labels(DataDictionary::GROUP_PURCHASE_STATUS, $purchase->account_id, true),
        ]);
    }

    public function void(Request $request, int $purchase): RedirectResponse
    {
        $purchase = $this->purchaseForAccount($this->currentAccountId($request), $purchase, ['items']);
        $this->warehouseInventoryService->voidPurchase($purchase);

        return redirect()
            ->route('purchases.show', $purchase)
            ->with('status', 'Purchase voided successfully.');
    }

    protected function validatePurchase(Request $request, int $accountId): array
    {
        $data = $request->validate([
            'vendor_id' => [
                'nullable',
                'integer',
                Rule::exists('tbl_vendors', 'id')->where(fn ($query) => $query->where('account_id', $accountId)),
            ],
            'warehouse_id' => [
                'required',
                'integer',
                Rule::exists('tbl_warehouses', 'id')->where(fn ($query) => $query->where('account_id', $accountId)),
            ],
            'invoice_number' => ['nullable', 'string', 'max:100'],
            'purchase_date' => ['required', 'regex:/^\d{2}-\d{2}-\d{4}$/'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => [
                'required',
                'integer',
                Rule::exists('tbl_products', 'id')->where(fn ($query) => $query->where('account_id', $accountId)),
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.line_total' => ['required', 'numeric', 'min:0'],
        ]);

        $data['purchase_date'] = $this->normalizeDateInput($data['purchase_date'] ?? null, 'purchase_date');

        return $data;
    }

    protected function purchaseForAccount(int $accountId, int $purchaseId, array $with = []): Purchase
    {
        return Purchase::query()
            ->where('account_id', $accountId)
            ->with($with)
            ->findOrFail($purchaseId);
    }

    protected function vendorsForAccount(int $accountId)
    {
        return Vendor::query()
            ->where('account_id', $accountId)
            ->orderBy('vendor_name')
            ->get();
    }

    protected function warehousesForAccount(int $accountId)
    {
        return Warehouse::query()
            ->where('account_id', $accountId)
            ->orderBy('warehouse_name')
            ->get();
    }

    protected function productsForAccount(int $accountId)
    {
        return Product::query()
            ->where('account_id', $accountId)
            ->orderBy('product_name')
            ->get();
    }
}
