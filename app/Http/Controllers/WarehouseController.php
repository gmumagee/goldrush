<?php

namespace App\Http\Controllers;

use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WarehouseController extends Controller
{
    public function index(Request $request): View
    {
        $accountId = (int) $request->session()->get('current_account_id');

        $warehouses = Warehouse::query()
            ->where('account_id', $accountId)
            ->orderBy('id', 'desc')
            ->get();

        return view('warehouses.index', compact('warehouses'));
    }

    public function create(): View
    {
        return view('warehouses.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $accountId = (int) $request->session()->get('current_account_id');

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
}
