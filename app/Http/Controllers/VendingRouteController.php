<?php

namespace App\Http\Controllers;

use App\Models\VendingRoute;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VendingRouteController extends Controller
{
    public function index(Request $request): View
    {
        $accountId = (int) $request->session()->get('current_account_id');

        $routes = VendingRoute::query()
            ->where('account_id', $accountId)
            ->orderBy('id', 'desc')
            ->get();

        return view('routes.index', compact('routes'));
    }

    public function create(): View
    {
        return view('routes.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $accountId = (int) $request->session()->get('current_account_id');

        $data = $request->validate([
            'route_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $data['account_id'] = $accountId;

        VendingRoute::create($data);

        return redirect()->route('routes.index')->with('status', 'Route created successfully.');
    }
}
