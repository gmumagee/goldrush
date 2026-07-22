<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Product::class);

        $accountId = $this->currentAccountId($request);
        $search = trim((string) $request->string('search'));

        $products = Product::query()
            ->where('account_id', $accountId)
            ->with('vendor')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($productQuery) use ($search) {
                    $productQuery
                        ->where('sku', 'like', '%'.$search.'%')
                        ->orWhere('product_name', 'like', '%'.$search.'%')
                        ->orWhere('brand', 'like', '%'.$search.'%')
                        ->orWhere('category', 'like', '%'.$search.'%')
                        ->orWhere('barcode', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('category')
            ->orderBy('product_name')
            ->get();

        $productsByCategory = $products
            ->groupBy(fn (Product $product) => trim((string) $product->category) !== '' ? $product->category : 'Uncategorized')
            ->sortKeys();

        return view('products.index', compact('productsByCategory', 'search'));
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Product::class);

        $accountId = $this->currentAccountId($request);

        $vendors = $this->vendorsForAccount($accountId);

        return view('products.create', compact('vendors'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Product::class);

        $accountId = $this->currentAccountId($request);
        $this->normalizeSku($request);

        $data = $request->validate([
            'vendor_id' => [
                'nullable',
                'integer',
                Rule::exists('tbl_vendors', 'id')->where(fn ($query) => $query->where('account_id', $accountId)),
            ],
            'category' => ['nullable', 'string', 'max:100'],
            'brand' => ['nullable', 'string', 'max:100'],
            'sku' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('tbl_products', 'sku')->where(fn ($query) => $query->where('account_id', $accountId)),
            ],
            'product_name' => ['required', 'string', 'max:255'],
            'size' => ['nullable', 'string', 'max:100'],
            'package_type' => ['nullable', 'string', 'max:100'],
            'barcode' => ['nullable', 'string', 'max:100'],
        ]);

        $data['account_id'] = $accountId;

        Product::create($data);

        return redirect()->route('products.index')->with('status', 'Product created successfully.');
    }

    public function show(Request $request, int $product): View
    {
        $product = $this->productForAccount($this->currentAccountId($request), $product, ['vendor', 'bins.machine', 'transactions']);
        $this->authorize('view', $product);

        return view('products.show', compact('product'));
    }

    public function edit(Request $request, int $product): View
    {
        $accountId = $this->currentAccountId($request);
        $product = $this->productForAccount($accountId, $product);
        $this->authorize('update', $product);

        return view('products.edit', [
            'product' => $product,
            'vendors' => $this->vendorsForAccount($accountId),
        ]);
    }

    public function update(Request $request, int $product): RedirectResponse
    {
        $accountId = $this->currentAccountId($request);
        $product = $this->productForAccount($accountId, $product);
        $this->authorize('update', $product);
        $this->normalizeSku($request);

        $data = $request->validate([
            'vendor_id' => [
                'nullable',
                'integer',
                Rule::exists('tbl_vendors', 'id')->where(fn ($query) => $query->where('account_id', $accountId)),
            ],
            'category' => ['nullable', 'string', 'max:100'],
            'brand' => ['nullable', 'string', 'max:100'],
            'sku' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('tbl_products', 'sku')
                    ->where(fn ($query) => $query->where('account_id', $accountId))
                    ->ignore($product->id),
            ],
            'product_name' => ['required', 'string', 'max:255'],
            'size' => ['nullable', 'string', 'max:100'],
            'package_type' => ['nullable', 'string', 'max:100'],
            'barcode' => ['nullable', 'string', 'max:100'],
        ]);

        $product->update($data);

        return redirect()->route('products.show', $product)->with('status', 'Product updated successfully.');
    }

    public function destroy(Request $request, int $product): RedirectResponse
    {
        $product = $this->productForAccount($this->currentAccountId($request), $product, ['bins', 'transactions']);
        $this->authorize('delete', $product);

        if ($product->bins()->exists() || $product->transactions()->exists()) {
            return back()->withErrors([
                'product' => 'Product cannot be deleted because it is used by bins or transactions.',
            ]);
        }

        $product->delete();

        return redirect()->route('products.index')->with('status', 'Product deleted successfully.');
    }

    protected function productForAccount(int $accountId, int $productId, array $with = []): Product
    {
        return Product::query()
            ->where('account_id', $accountId)
            ->with($with)
            ->findOrFail($productId);
    }

    protected function vendorsForAccount(int $accountId)
    {
        return Vendor::query()
            ->where('account_id', $accountId)
            ->orderBy('vendor_name')
            ->get();
    }

    protected function normalizeSku(Request $request): void
    {
        $request->merge([
            'sku' => ($sku = trim((string) $request->input('sku'))) !== '' ? $sku : null,
        ]);
    }
}
