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
        $accountId = (int) $request->session()->get('current_account_id');

        $vendors = Vendor::query()
            ->where('account_id', $accountId)
            ->orderBy('id', 'desc')
            ->get();

        return view('vendors.index', compact('vendors'));
    }

    public function create(): View
    {
        return view('vendors.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $accountId = (int) $request->session()->get('current_account_id');

        $data = $request->validate([
            'vendor_name' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
        ]);

        $data['account_id'] = $accountId;

        Vendor::create($data);

        return redirect()->route('vendors.index')->with('status', 'Vendor created successfully.');
    }
}
