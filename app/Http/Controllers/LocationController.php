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
        $accountId = $this->currentAccountId($request);
        $search = trim((string) $request->string('search'));

        $locations = Location::query()
            ->where('account_id', $accountId)
            ->with('route')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($locationQuery) use ($search) {
                    $locationQuery
                        ->where('location_name', 'like', '%'.$search.'%')
                        ->orWhere('city', 'like', '%'.$search.'%')
                        ->orWhere('contact_name', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('id', 'desc')
            ->paginate(25)
            ->withQueryString();

        return view('locations.index', compact('locations', 'search'));
    }

    public function create(Request $request): View
    {
        $accountId = $this->currentAccountId($request);

        $routes = $this->routesForAccount($accountId);

        return view('locations.create', compact('routes'));
    }

    public function store(Request $request): RedirectResponse
    {
        $accountId = $this->currentAccountId($request);

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

    public function show(Request $request, int $location): View
    {
        $location = $this->locationForAccount($this->currentAccountId($request), $location, [
            'route',
            'machines.bins',
            'services.user',
        ]);

        return view('locations.show', compact('location'));
    }

    public function edit(Request $request, int $location): View
    {
        $accountId = $this->currentAccountId($request);
        $location = $this->locationForAccount($accountId, $location);

        return view('locations.edit', [
            'location' => $location,
            'routes' => $this->routesForAccount($accountId),
        ]);
    }

    public function update(Request $request, int $location): RedirectResponse
    {
        $accountId = $this->currentAccountId($request);
        $location = $this->locationForAccount($accountId, $location);

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

        $location->update($data);

        return redirect()->route('locations.show', $location)->with('status', 'Location updated successfully.');
    }

    public function destroy(Request $request, int $location): RedirectResponse
    {
        $location = $this->locationForAccount($this->currentAccountId($request), $location, ['machines', 'services']);

        if ($location->machines()->exists() || $location->services()->exists()) {
            return back()->withErrors([
                'location' => 'Location cannot be deleted because it has machines or services.',
            ]);
        }

        $location->delete();

        return redirect()->route('locations.index')->with('status', 'Location deleted successfully.');
    }

    protected function locationForAccount(int $accountId, int $locationId, array $with = []): Location
    {
        return Location::query()
            ->where('account_id', $accountId)
            ->with($with)
            ->findOrFail($locationId);
    }

    protected function routesForAccount(int $accountId)
    {
        return VendingRoute::query()
            ->where('account_id', $accountId)
            ->orderBy('route_name')
            ->get();
    }
}
