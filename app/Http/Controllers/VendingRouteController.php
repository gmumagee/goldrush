<?php

namespace App\Http\Controllers;

use App\Models\DataDictionary;
use App\Models\Location;
use App\Models\RouteLocation;
use App\Models\VendingRoute;
use App\Services\DataDictionaryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VendingRouteController extends Controller
{
    public function __construct(protected DataDictionaryService $dataDictionaryService)
    {
    }

    public function index(Request $request): View
    {
        $accountId = $this->currentAccountId($request);
        $search = trim((string) $request->string('search'));
        $scheduledDayOptions = $this->scheduledDayOptions($accountId);

        $routes = VendingRoute::query()
            ->where('account_id', $accountId)
            ->withCount('routeLocations as stops_count')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($routeQuery) use ($search) {
                    $routeQuery
                        ->where('route_name', 'like', '%'.$search.'%')
                        ->orWhere('description', 'like', '%'.$search.'%');
                });
            })
            ->orderByRaw($this->scheduledDayOrderSql($scheduledDayOptions))
            ->orderBy('route_name')
            ->paginate(25)
            ->withQueryString();

        $routesByScheduledDay = VendingRoute::query()
            ->where('account_id', $accountId)
            ->withCount('routeLocations as stops_count')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($routeQuery) use ($search) {
                    $routeQuery
                        ->where('route_name', 'like', '%'.$search.'%')
                        ->orWhere('description', 'like', '%'.$search.'%');
                });
            })
            ->orderByRaw($this->scheduledDayOrderSql($scheduledDayOptions))
            ->orderBy('route_name')
            ->get()
            ->groupBy('scheduled_day');

        return view('routes.index', [
            'routes' => $routes,
            'search' => $search,
            'routesByScheduledDay' => $routesByScheduledDay,
            'scheduledDayOptions' => $scheduledDayOptions,
            'scheduledDayLabels' => $this->dataDictionaryService->labels(DataDictionary::GROUP_ROUTE_SCHEDULED_DAY, $accountId, false),
        ]);
    }

    public function create(Request $request): View
    {
        return view('routes.create', [
            'scheduledDayOptions' => $this->scheduledDayOptions($this->currentAccountId($request)),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $accountId = $this->currentAccountId($request);

        $data = $request->validate([
            'route_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'scheduled_day' => ['required', 'string', $this->activeDictionaryValueRule(DataDictionary::GROUP_ROUTE_SCHEDULED_DAY, $accountId)],
        ]);

        $data['account_id'] = $accountId;

        VendingRoute::create($data);

        return redirect()->route('routes.index')->with('status', 'Route created successfully.');
    }

    public function show(Request $request, int $route): View
    {
        $accountId = $this->currentAccountId($request);
        $route = $this->routeForAccount($accountId, $route, [
            'routeLocations.location',
        ]);

        $assignedLocationIds = $route->routeLocations->pluck('location_id');
        $availableLocations = Location::query()
            ->where('account_id', $accountId)
            ->whereNotIn('id', $assignedLocationIds)
            ->orderBy('location_name')
            ->get();

        return view('routes.show', [
            'route' => $route,
            'availableLocations' => $availableLocations,
            'scheduledDayLabels' => $this->dataDictionaryService->labels(DataDictionary::GROUP_ROUTE_SCHEDULED_DAY, $accountId, false),
        ]);
    }

    public function edit(Request $request, int $route): View
    {
        $accountId = $this->currentAccountId($request);
        $route = $this->routeForAccount($accountId, $route);

        return view('routes.edit', [
            'route' => $route,
            'scheduledDayOptions' => $this->scheduledDayOptions($accountId),
        ]);
    }

    public function update(Request $request, int $route): RedirectResponse
    {
        $route = $this->routeForAccount($this->currentAccountId($request), $route);

        $data = $request->validate([
            'route_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'scheduled_day' => ['required', 'string', $this->activeDictionaryValueRule(DataDictionary::GROUP_ROUTE_SCHEDULED_DAY, $this->currentAccountId($request))],
        ]);

        $route->update($data);

        return redirect()->route('routes.show', $route)->with('status', 'Route updated successfully.');
    }

    public function destroy(Request $request, int $route): RedirectResponse
    {
        $route = $this->routeForAccount($this->currentAccountId($request), $route, ['routeLocations']);

        if ($route->routeLocations()->exists()) {
            return back()->withErrors([
                'route' => 'Route cannot be deleted because it has assigned stops.',
            ]);
        }

        $route->delete();

        return redirect()->route('routes.index')->with('status', 'Route deleted successfully.');
    }

    protected function routeForAccount(int $accountId, int $routeId, array $with = []): VendingRoute
    {
        return VendingRoute::query()
            ->where('account_id', $accountId)
            ->with($with)
            ->findOrFail($routeId);
    }

    protected function scheduledDayOptions(int $accountId): Collection
    {
        return $this->dataDictionaryService->options(DataDictionary::GROUP_ROUTE_SCHEDULED_DAY, $accountId);
    }

    protected function scheduledDayOrderSql(Collection $scheduledDayOptions): string
    {
        $cases = $scheduledDayOptions
            ->values()
            ->map(function ($option, $index) {
                $day = str_replace("'", "''", $option->value);

                return "WHEN scheduled_day = '{$day}' THEN ".($index + 1);
            })
            ->implode(' ');

        return "CASE {$cases} ELSE 999 END";
    }
}
