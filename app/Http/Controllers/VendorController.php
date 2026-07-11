<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VendorController extends Controller
{
    public function index(Request $request): View
    {
        $accountId = $this->currentAccountId($request);
        $search = trim((string) $request->string('search'));

        $vendors = Vendor::query()
            ->where('account_id', $accountId)
            ->withCount('products')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($vendorQuery) use ($search) {
                    $vendorQuery
                        ->where('vendor_name', 'like', '%'.$search.'%')
                        ->orWhere('location', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('id', 'desc')
            ->paginate(25)
            ->withQueryString();

        return view('vendors.index', compact('vendors', 'search'));
    }

    public function create(): View
    {
        return view('vendors.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $accountId = $this->currentAccountId($request);

        $data = $request->validate([
            'vendor_name' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
        ]);

        $data['account_id'] = $accountId;

        Vendor::create($data);

        return redirect()->route('vendors.index')->with('status', 'Vendor created successfully.');
    }

    public function show(Request $request, int $vendor): View
    {
        $vendor = $this->vendorForAccount($this->currentAccountId($request), $vendor, ['products']);

        return view('vendors.show', compact('vendor'));
    }

    public function edit(Request $request, int $vendor): View
    {
        $vendor = $this->vendorForAccount($this->currentAccountId($request), $vendor);

        return view('vendors.edit', compact('vendor'));
    }

    public function update(Request $request, int $vendor): RedirectResponse
    {
        $vendor = $this->vendorForAccount($this->currentAccountId($request), $vendor);

        $data = $request->validate([
            'vendor_name' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
        ]);

        $vendor->update($data);

        return redirect()->route('vendors.show', $vendor)->with('status', 'Vendor updated successfully.');
    }

    public function destroy(Request $request, int $vendor): RedirectResponse
    {
        $vendor = $this->vendorForAccount($this->currentAccountId($request), $vendor, ['products']);

        if ($vendor->products()->exists()) {
            return back()->withErrors([
                'vendor' => 'Vendor cannot be deleted because it has products.',
            ]);
        }

        $vendor->delete();

        return redirect()->route('vendors.index')->with('status', 'Vendor deleted successfully.');
    }

    protected function vendorForAccount(int $accountId, int $vendorId, array $with = []): Vendor
    {
        return Vendor::query()
            ->where('account_id', $accountId)
            ->with($with)
            ->findOrFail($vendorId);
    }
}
