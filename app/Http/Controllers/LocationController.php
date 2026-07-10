<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\VendingRoute;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LocationController extends Controller
{
    public function index(Request $request): View
    {
        $accountId = (int) $request->session()->get('current_account_id');

        $locations = Location::query()
            ->where('account_id', $accountId)
            ->with('route')
            ->orderBy('id', 'desc')
            ->get();

        return view('locations.index', compact('locations'));
    }

    public function create(Request $request): View
    {
        $accountId = (int) $request->session()->get('current_account_id');

        $routes = VendingRoute::query()
            ->where('account_id', $accountId)
            ->orderBy('route_name')
            ->get();

        return view('locations.create', compact('routes'));
    }

    public function store(Request $request): RedirectResponse
    {
        $accountId = (int) $request->session()->get('current_account_id');

        $data = $request->validate([
            'route_id' => [
                'required',
                'integer',
                Rule::exists('tbl_routes', 'id')->where(fn ($query) => $query->where('account_id', $accountId)),
            ],
            'location_name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'zip_code' => ['nullable', 'string', 'max:20'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'contact_email' => ['nullable', 'email', 'max:255'],
        ]);

        $data['account_id'] = $accountId;

        Location::create($data);

        return redirect()->route('locations.index')->with('status', 'Location created successfully.');
    }
}
