<?php

namespace App\Http\Controllers;

use App\Models\AccountUser;
use App\Models\DataDictionary;
use App\Models\Location;
use App\Models\RouteLocation;
use App\Models\VendingRoute;
use App\Services\DataDictionaryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LocationController extends Controller
{
    public function __construct(protected DataDictionaryService $dataDictionaryService)
    {
    }

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
                'nullable',
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

        DB::transaction(function () use ($data, $accountId) {
            $location = Location::create($data);
            $this->syncPrimaryRouteMembership($accountId, $location, null, $location->route_id ? (int) $location->route_id : null);
        });

        return redirect()->route('locations.index')->with('status', 'Location created successfully.');
    }

    public function show(Request $request, int $location): View
    {
        $accountId = $this->currentAccountId($request);
        $location = $this->locationForAccount($accountId, $location, [
            'route',
            'routes',
            'machines.bins',
            'services.user',
            'locationContacts.contact',
            'documents.uploadedBy',
        ]);

        $membership = AccountUser::query()
            ->where('account_id', $accountId)
            ->where('user_id', $request->user()->id)
            ->where('status', AccountUser::STATUS_ACTIVE)
            ->first();

        abort_if(! $membership, 403);

        return view('locations.show', [
            'location' => $location,
            'locationContactRoleLabels' => $this->dataDictionaryService->labels(DataDictionary::GROUP_LOCATION_CONTACT_ROLE, $accountId, true),
            'locationDocumentTypeLabels' => $this->dataDictionaryService->labels(DataDictionary::GROUP_LOCATION_DOCUMENT_TYPE, $accountId, true),
            'canManageDocuments' => $membership->roleMatches(AccountUser::ROLE_OWNER)
                || $membership->roleMatches(AccountUser::ROLE_ADMIN)
                || $membership->roleMatches(AccountUser::ROLE_MANAGER),
        ]);
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
                'nullable',
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

        DB::transaction(function () use ($location, $data, $accountId) {
            $previousRouteId = $location->route_id ? (int) $location->route_id : null;
            $location->update($data);
            $this->syncPrimaryRouteMembership($accountId, $location, $previousRouteId, $location->route_id ? (int) $location->route_id : null);
        });

        return redirect()->route('locations.show', $location)->with('status', 'Location updated successfully.');
    }

    public function destroy(Request $request, int $location): RedirectResponse
    {
        $location = $this->locationForAccount($this->currentAccountId($request), $location, ['machines', 'services', 'routeLocations', 'documents']);

        if ($location->machines()->exists() || $location->services()->exists()) {
            return back()->withErrors([
                'location' => 'Location cannot be deleted because it has machines or services.',
            ]);
        }

        if ($location->routeLocations()->exists()) {
            return back()->withErrors([
                'location' => 'Location cannot be deleted because it is assigned to a route.',
            ]);
        }

        DB::transaction(function () use ($location) {
            foreach ($location->documents as $document) {
                $document->deleteStoredFile();
                $document->delete();
            }

            $location->delete();
        });

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

    protected function syncPrimaryRouteMembership(int $accountId, Location $location, ?int $previousRouteId, ?int $newRouteId): void
    {
        if ($previousRouteId !== null && $previousRouteId !== $newRouteId) {
            RouteLocation::query()
                ->where('account_id', $accountId)
                ->where('route_id', $previousRouteId)
                ->where('location_id', $location->id)
                ->delete();

            $this->renumberStops($accountId, $previousRouteId);
        }

        if ($newRouteId === null) {
            return;
        }

        $alreadyExists = RouteLocation::query()
            ->where('account_id', $accountId)
            ->where('route_id', $newRouteId)
            ->where('location_id', $location->id)
            ->exists();

        if ($alreadyExists) {
            return;
        }

        $nextStopOrder = (int) RouteLocation::query()
            ->where('account_id', $accountId)
            ->where('route_id', $newRouteId)
            ->max('stop_order') + 1;

        RouteLocation::create([
            'account_id' => $accountId,
            'route_id' => $newRouteId,
            'location_id' => $location->id,
            'stop_order' => $nextStopOrder,
        ]);
    }

    protected function renumberStops(int $accountId, int $routeId): void
    {
        $stops = RouteLocation::query()
            ->where('account_id', $accountId)
            ->where('route_id', $routeId)
            ->orderBy('stop_order')
            ->orderBy('id')
            ->get();

        if ($stops->isEmpty()) {
            return;
        }

        RouteLocation::query()
            ->whereIn('id', $stops->pluck('id'))
            ->update([
                'stop_order' => DB::raw('stop_order + 1000'),
            ]);

        foreach ($stops->values() as $index => $stop) {
            RouteLocation::query()
                ->where('id', $stop->id)
                ->update([
                    'stop_order' => $index + 1,
                ]);
        }
    }
}
