<?php

namespace App\Http\Controllers;

use App\Models\AccountUser;
use App\Models\DataDictionary;
use App\Models\Location;
use App\Models\RouteLocation;
use App\Models\User;
use App\Models\VendingRoute;
use App\Models\Warehouse;
use App\Services\DataDictionaryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
            ->when($scheduledDayOptions->isNotEmpty(), fn ($query) => $query->orderByRaw($this->scheduledDayOrderSql($scheduledDayOptions)))
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
            ->when($scheduledDayOptions->isNotEmpty(), fn ($query) => $query->orderByRaw($this->scheduledDayOrderSql($scheduledDayOptions)))
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
        $accountId = $this->currentAccountId($request);

        return view('routes.create', [
            'scheduledDayOptions' => $this->scheduledDayOptions($accountId),
            'warehouses' => $this->warehousesForAccount($accountId),
            'users' => $this->assignableUsersForAccount($accountId)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $accountId = $this->currentAccountId($request);
        $data = $this->validateRoute($request, $accountId);

        VendingRoute::create([
            'account_id' => $accountId,
            'route_name' => $data['route_name'],
            'description' => $data['description'] ?? null,
            'scheduled_day' => $data['scheduled_day'],
            'warehouse_id' => $data['warehouse_id'] ?? null,
            'assigned_user_id' => $data['assigned_user_id'] ?? null,
            'auto_schedule_enabled' => $data['auto_schedule_enabled'],
        ]);

        return redirect()->route('routes.index')->with('status', 'Route created successfully.');
    }

    public function show(Request $request, int $route): View
    {
        $accountId = $this->currentAccountId($request);
        $route = $this->routeForAccount($accountId, $route, [
            'warehouse',
            'assignedUser',
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
        $route = $this->routeForAccount($accountId, $route, ['warehouse', 'assignedUser']);

        return view('routes.edit', [
            'route' => $route,
            'scheduledDayOptions' => $this->scheduledDayOptions($accountId),
            'warehouses' => $this->warehousesForAccount($accountId),
            'users' => $this->assignableUsersForAccount($accountId)->get(),
        ]);
    }

    public function update(Request $request, int $route): RedirectResponse
    {
        $accountId = $this->currentAccountId($request);
        $route = $this->routeForAccount($accountId, $route);
        $data = $this->validateRoute($request, $accountId);

        $route->update([
            'route_name' => $data['route_name'],
            'description' => $data['description'] ?? null,
            'scheduled_day' => $data['scheduled_day'],
            'warehouse_id' => $data['warehouse_id'] ?? null,
            'assigned_user_id' => $data['assigned_user_id'] ?? null,
            'auto_schedule_enabled' => $data['auto_schedule_enabled'],
        ]);

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

    protected function validateRoute(Request $request, int $accountId): array
    {
        $autoScheduleEnabled = $request->boolean('auto_schedule_enabled', true);

        $data = $request->validate([
            'route_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'scheduled_day' => ['required', 'string', $this->activeDictionaryValueRule(DataDictionary::GROUP_ROUTE_SCHEDULED_DAY, $accountId)],
            'warehouse_id' => [
                Rule::requiredIf($autoScheduleEnabled),
                'nullable',
                'integer',
                Rule::exists('tbl_warehouses', 'id')->where(fn ($query) => $query->where('account_id', $accountId)),
            ],
            'assigned_user_id' => ['nullable', 'integer'],
            'auto_schedule_enabled' => ['nullable', 'boolean'],
        ]);

        $assignedUserId = isset($data['assigned_user_id']) && $data['assigned_user_id'] !== null
            ? (int) $data['assigned_user_id']
            : null;

        if ($assignedUserId !== null) {
            $this->ensureUserBelongsToAccount($accountId, $assignedUserId);
        }

        $data['warehouse_id'] = isset($data['warehouse_id']) && $data['warehouse_id'] !== null
            ? (int) $data['warehouse_id']
            : null;
        $data['assigned_user_id'] = $assignedUserId;
        $data['auto_schedule_enabled'] = $autoScheduleEnabled;

        return $data;
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

    protected function warehousesForAccount(int $accountId): Collection
    {
        return Warehouse::query()
            ->where('account_id', $accountId)
            ->orderBy('warehouse_name')
            ->get();
    }

    protected function assignableUsersForAccount(int $accountId)
    {
        return User::query()
            ->select('tbl_users.*')
            ->join('tbl_account_users', 'tbl_account_users.user_id', '=', 'tbl_users.id')
            ->where('tbl_account_users.account_id', $accountId)
            ->where('tbl_account_users.status', AccountUser::STATUS_ACTIVE)
            ->where('tbl_users.status', User::STATUS_ACTIVE)
            ->distinct()
            ->orderBy('tbl_users.name');
    }

    protected function ensureUserBelongsToAccount(int $accountId, int $userId): void
    {
        if (! $this->assignableUsersForAccount($accountId)->where('tbl_users.id', $userId)->exists()) {
            throw ValidationException::withMessages([
                'assigned_user_id' => 'The selected technician is not available for this account.',
            ]);
        }
    }
}
