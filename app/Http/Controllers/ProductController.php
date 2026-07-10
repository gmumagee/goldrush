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
        $accountId = (int) $request->session()->get('current_account_id');

        $products = Product::query()
            ->where('account_id', $accountId)
            ->with('vendor')
            ->orderBy('id', 'desc')
            ->get();

        return view('products.index', compact('products'));
    }

    public function create(Request $request): View
    {
        $accountId = (int) $request->session()->get('current_account_id');

        $vendors = Vendor::query()
            ->where('account_id', $accountId)
            ->orderBy('vendor_name')
            ->get();

        return view('products.create', compact('vendors'));
    }

    public function store(Request $request): RedirectResponse
    {
        $accountId = (int) $request->session()->get('current_account_id');

        $data = $request->validate([
            'vendor_id' => [
                'nullable',
                'integer',
                Rule::exists('tbl_vendors', 'id')->where(fn ($query) => $query->where('account_id', $accountId)),
            ],
            'sku' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('tbl_products', 'sku')->where(fn ($query) => $query->where('account_id', $accountId)),
            ],
            'product_name' => ['required', 'string', 'max:255'],
            'barcode' => ['nullable', 'string', 'max:100'],
        ]);

        $data['account_id'] = $accountId;

        Product::create($data);

        return redirect()->route('products.index')->with('status', 'Product created successfully.');
    }
}
