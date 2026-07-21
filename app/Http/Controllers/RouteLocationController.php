<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\RouteLocation;
use App\Models\VendingRoute;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RouteLocationController extends Controller
{
    public function store(Request $request, int $route): RedirectResponse
    {
        $accountId = $this->currentAccountId($request);
        $route = $this->routeForAccount($accountId, $route);
        $this->authorize('update', $route);

        $data = $request->validate([
            'location_id' => ['required', 'integer'],
        ]);

        DB::transaction(function () use ($accountId, $route, $data) {
            $location = Location::query()
                ->where('account_id', $accountId)
                ->findOrFail((int) $data['location_id']);

            $alreadyExists = RouteLocation::query()
                ->where('account_id', $accountId)
                ->where('route_id', $route->id)
                ->where('location_id', $location->id)
                ->exists();

            if ($alreadyExists) {
                throw ValidationException::withMessages([
                    'location_id' => 'This location is already on this route.',
                ]);
            }

            $nextStopOrder = (int) RouteLocation::query()
                ->where('account_id', $accountId)
                ->where('route_id', $route->id)
                ->max('stop_order') + 1;

            $hasPrimaryRoute = $location->routeLocations()
                ->where('account_id', $accountId)
                ->where('is_primary', true)
                ->exists();

            RouteLocation::create([
                'account_id' => $accountId,
                'route_id' => $route->id,
                'location_id' => $location->id,
                'stop_order' => $nextStopOrder,
                'is_primary' => ! $hasPrimaryRoute,
            ]);
        });

        return redirect()
            ->route('routes.show', $route)
            ->with('status', 'Location added to route.');
    }

    public function destroy(Request $request, int $route, int $routeLocation): RedirectResponse
    {
        $accountId = $this->currentAccountId($request);
        $route = $this->routeForAccount($accountId, $route);
        $routeLocation = $this->routeLocationForRoute($accountId, $route->id, $routeLocation, ['location.routeLocations']);
        $this->authorize('delete', $routeLocation);

        DB::transaction(function () use ($accountId, $route, $routeLocation) {
            $location = $routeLocation->location;
            $wasPrimary = (bool) $routeLocation->is_primary;

            $routeLocation->delete();
            $this->renumberStops($accountId, $route->id);

            if ($location && $wasPrimary) {
                $replacementPrimaryId = $location->routeLocations()
                    ->where('account_id', $accountId)
                    ->orderBy('stop_order')
                    ->orderBy('id')
                    ->value('id');

                if ($replacementPrimaryId !== null) {
                    RouteLocation::query()
                        ->where('id', $replacementPrimaryId)
                        ->update([
                            'is_primary' => true,
                        ]);
                }
            }
        });

        return redirect()
            ->route('routes.show', $route)
            ->with('status', 'Location removed from route.');
    }

    public function moveUp(Request $request, int $route, int $routeLocation): RedirectResponse
    {
        $accountId = $this->currentAccountId($request);
        $route = $this->routeForAccount($accountId, $route);
        $routeLocation = $this->routeLocationForRoute($accountId, $route->id, $routeLocation);
        $this->authorize('update', $routeLocation);

        DB::transaction(function () use ($accountId, $route, $routeLocation) {
            $stops = $this->orderedStops($accountId, $route->id);
            $currentIndex = $stops->search(fn (RouteLocation $stop) => $stop->id === $routeLocation->id);

            if ($currentIndex === false || $currentIndex === 0) {
                return;
            }

            $reordered = $stops->values()->all();
            [$reordered[$currentIndex - 1], $reordered[$currentIndex]] = [$reordered[$currentIndex], $reordered[$currentIndex - 1]];

            $this->persistStopOrder(collect($reordered));
        });

        return redirect()
            ->route('routes.show', $route)
            ->with('status', 'Route stop order updated.');
    }

    public function moveDown(Request $request, int $route, int $routeLocation): RedirectResponse
    {
        $accountId = $this->currentAccountId($request);
        $route = $this->routeForAccount($accountId, $route);
        $routeLocation = $this->routeLocationForRoute($accountId, $route->id, $routeLocation);
        $this->authorize('update', $routeLocation);

        DB::transaction(function () use ($accountId, $route, $routeLocation) {
            $stops = $this->orderedStops($accountId, $route->id);
            $currentIndex = $stops->search(fn (RouteLocation $stop) => $stop->id === $routeLocation->id);

            if ($currentIndex === false || $currentIndex === $stops->count() - 1) {
                return;
            }

            $reordered = $stops->values()->all();
            [$reordered[$currentIndex], $reordered[$currentIndex + 1]] = [$reordered[$currentIndex + 1], $reordered[$currentIndex]];

            $this->persistStopOrder(collect($reordered));
        });

        return redirect()
            ->route('routes.show', $route)
            ->with('status', 'Route stop order updated.');
    }

    protected function routeForAccount(int $accountId, int $routeId): VendingRoute
    {
        return VendingRoute::query()
            ->where('account_id', $accountId)
            ->findOrFail($routeId);
    }

    protected function routeLocationForRoute(int $accountId, int $routeId, int $routeLocationId, array $with = []): RouteLocation
    {
        return RouteLocation::query()
            ->where('account_id', $accountId)
            ->where('route_id', $routeId)
            ->with($with)
            ->findOrFail($routeLocationId);
    }

    protected function orderedStops(int $accountId, int $routeId): Collection
    {
        return RouteLocation::query()
            ->where('account_id', $accountId)
            ->where('route_id', $routeId)
            ->orderBy('stop_order')
            ->orderBy('id')
            ->get();
    }

    protected function renumberStops(int $accountId, int $routeId): void
    {
        $this->persistStopOrder($this->orderedStops($accountId, $routeId));
    }

    protected function persistStopOrder(Collection $stops): void
    {
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
