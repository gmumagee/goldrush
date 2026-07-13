<x-app-layout title="Routes">
    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-7xl space-y-6">
            <div class="flex items-center justify-between gap-4">
                <div><h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 md:text-3xl">Routes</h1><p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Manage routes for the selected account.</p></div>
                <a href="{{ route('routes.create') }}" class="inline-flex items-center rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-violet-500">Add Route</a>
            </div>
            @if (session('status'))
                <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-900/60 dark:bg-green-500/10 dark:text-green-300">{{ session('status') }}</div>
            @endif
            <x-validation-errors />
            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Route Schedule</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Routes grouped by their scheduled service day.</p>
                    </div>
                </div>
                <div class="grid gap-5 p-5 lg:grid-cols-2">
                    @foreach ($scheduledDayOptions as $scheduledDay)
                        @php
                            $routesForDay = $routesByScheduledDay->get($scheduledDay->value, collect());
                        @endphp
                        <div class="rounded-2xl border border-gray-200 p-5 dark:border-gray-700/60">
                            <div class="flex items-center justify-between gap-4">
                                <h3 class="text-base font-semibold text-gray-800 dark:text-gray-100">{{ $scheduledDay->displayLabel() }}</h3>
                                <span class="text-sm text-gray-500 dark:text-gray-400">{{ $routesForDay->count() }} route{{ $routesForDay->count() === 1 ? '' : 's' }}</span>
                            </div>
                            <div class="mt-4 space-y-3">
                                @forelse ($routesForDay as $route)
                                    <div class="rounded-xl bg-gray-50 px-4 py-3 dark:bg-gray-800/70">
                                        <div class="flex items-start justify-between gap-4">
                                            <div>
                                                <div class="font-medium text-gray-800 dark:text-gray-100">{{ $route->route_name }}</div>
                                                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $route->stops_count }} stop{{ $route->stops_count === 1 ? '' : 's' }}</div>
                                            </div>
                                            <div class="flex flex-wrap gap-2">
                                                <a href="{{ route('routes.show', $route) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Manage Stops</a>
                                                <a href="{{ route('routes.edit', $route) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Edit</a>
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-sm text-gray-500 dark:text-gray-400">No routes scheduled for {{ $scheduledDay->displayLabel() }}.</p>
                                @endforelse
                            </div>
                        </div>
                    @endforeach
                    @php
                        $unscheduledRoutes = $routesByScheduledDay->get('', collect())->merge($routesByScheduledDay->get(null, collect()));
                    @endphp
                    @if ($unscheduledRoutes->isNotEmpty())
                        <div class="rounded-2xl border border-gray-200 p-5 dark:border-gray-700/60">
                            <div class="flex items-center justify-between gap-4">
                                <h3 class="text-base font-semibold text-gray-800 dark:text-gray-100">Unscheduled</h3>
                                <span class="text-sm text-gray-500 dark:text-gray-400">{{ $unscheduledRoutes->count() }} route{{ $unscheduledRoutes->count() === 1 ? '' : 's' }}</span>
                            </div>
                            <div class="mt-4 space-y-3">
                                @foreach ($unscheduledRoutes as $route)
                                    <div class="rounded-xl bg-gray-50 px-4 py-3 dark:bg-gray-800/70">
                                        <div class="flex items-start justify-between gap-4">
                                            <div>
                                                <div class="font-medium text-gray-800 dark:text-gray-100">{{ $route->route_name }}</div>
                                                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $route->stops_count }} stop{{ $route->stops_count === 1 ? '' : 's' }}</div>
                                            </div>
                                            <div class="flex flex-wrap gap-2">
                                                <a href="{{ route('routes.show', $route) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Manage Stops</a>
                                                <a href="{{ route('routes.edit', $route) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Edit</a>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </section>
            <section class="panel">
                <div class="panel-body border-b border-gray-200 dark:border-gray-700/60">
                    <form method="GET" action="{{ route('routes.index') }}" class="grid gap-4 md:grid-cols-[1fr_auto]">
                        <x-input name="search" type="text" :value="$search" placeholder="Search route name or description" />
                        <div class="flex gap-3"><x-button>Search</x-button><a href="{{ route('routes.index') }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Reset</a></div>
                    </form>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700/60">
                        <thead class="bg-gray-50 dark:bg-gray-800/80"><tr><th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Route Name</th><th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Scheduled Day</th><th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Number of Stops</th><th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Description</th><th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Actions</th></tr></thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700/60">
                            @forelse ($routes as $route)
                                <tr class="bg-white dark:bg-gray-800">
                                    <td class="px-5 py-4 font-medium text-gray-800 dark:text-gray-100">{{ $route->route_name }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $scheduledDayLabels[$route->scheduled_day] ?? ($route->scheduled_day ?: '—') }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $route->stops_count }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $route->description ?: '—' }}</td>
                                    <td class="px-5 py-4"><div class="flex flex-wrap gap-2"><a href="{{ route('routes.show', $route) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Manage Stops</a><a href="{{ route('routes.edit', $route) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Edit</a><form method="POST" action="{{ route('routes.destroy', $route) }}">@csrf @method('DELETE') <button type="submit" class="inline-flex items-center rounded-xl border border-red-300 px-3 py-1.5 text-xs font-medium text-red-700 transition hover:bg-red-50 dark:border-red-500/40 dark:text-red-300 dark:hover:bg-red-500/10">Delete</button></form></div></td>
                                </tr>
                            @empty
                                <tr class="bg-white dark:bg-gray-800"><td colspan="5" class="px-5 py-8 text-center text-gray-500 dark:text-gray-400">No routes found for this account.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="panel-body">{{ $routes->links() }}</div>
            </section>
        </div>
    </div>
</x-app-layout>
