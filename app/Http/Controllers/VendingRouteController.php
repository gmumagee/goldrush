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
        $accountId = $this->currentAccountId($request);
        $search = trim((string) $request->string('search'));

        $routes = VendingRoute::query()
            ->where('account_id', $accountId)
            ->withCount('locations')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($routeQuery) use ($search) {
                    $routeQuery
                        ->where('route_name', 'like', '%'.$search.'%')
                        ->orWhere('description', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('id', 'desc')
            ->paginate(25)
            ->withQueryString();

        return view('routes.index', compact('routes', 'search'));
    }

    public function create(): View
    {
        return view('routes.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $accountId = $this->currentAccountId($request);

        $data = $request->validate([
            'route_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $data['account_id'] = $accountId;

        VendingRoute::create($data);

        return redirect()->route('routes.index')->with('status', 'Route created successfully.');
    }

    public function show(Request $request, int $route): View
    {
        $route = $this->routeForAccount($this->currentAccountId($request), $route, ['locations']);

        return view('routes.show', compact('route'));
    }

    public function edit(Request $request, int $route): View
    {
        $route = $this->routeForAccount($this->currentAccountId($request), $route);

        return view('routes.edit', compact('route'));
    }

    public function update(Request $request, int $route): RedirectResponse
    {
        $route = $this->routeForAccount($this->currentAccountId($request), $route);

        $data = $request->validate([
            'route_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $route->update($data);

        return redirect()->route('routes.show', $route)->with('status', 'Route updated successfully.');
    }

    public function destroy(Request $request, int $route): RedirectResponse
    {
        $route = $this->routeForAccount($this->currentAccountId($request), $route, ['locations']);

        if ($route->locations()->exists()) {
            return back()->withErrors([
                'route' => 'Route cannot be deleted because it has locations.',
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
}
