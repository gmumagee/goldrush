<x-app-layout title="Route Details">
    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-7xl space-y-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 md:text-3xl">{{ $route->route_name }}</h1>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ $scheduledDayLabels[$route->scheduled_day] ?? $route->scheduled_day ?? 'No scheduled day' }}</p>
                </div>
                <div class="flex gap-3">
                    <a href="{{ route('routes.index') }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Back to Routes</a>
                    <a href="{{ route('calendar-events.create', ['source_type' => 'route', 'source_id' => $route->id]) }}" class="inline-flex items-center rounded-xl border border-violet-300 px-4 py-2 text-sm font-medium text-violet-700 transition hover:bg-violet-50 dark:border-violet-500/40 dark:text-violet-300 dark:hover:bg-violet-500/10">Schedule Event</a>
                    <a href="{{ route('routes.edit', $route) }}" class="inline-flex items-center rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-violet-500">Edit Route</a>
                </div>
            </div>

            @if (session('status'))
                <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-900/60 dark:bg-green-500/10 dark:text-green-300">{{ session('status') }}</div>
            @endif

            <x-validation-errors />

            <section class="panel">
                <div class="panel-body">
                    <dl class="grid gap-4 text-sm md:grid-cols-2 xl:grid-cols-3">
                        <div><dt class="text-gray-500 dark:text-gray-400">Scheduled Day</dt><dd class="mt-1 text-gray-800 dark:text-gray-100">{{ $scheduledDayLabels[$route->scheduled_day] ?? ($route->scheduled_day ?: '—') }}</dd></div>
                        <div><dt class="text-gray-500 dark:text-gray-400">Number of Stops</dt><dd class="mt-1 text-gray-800 dark:text-gray-100">{{ $route->routeLocations->count() }}</dd></div>
                        <div><dt class="text-gray-500 dark:text-gray-400">Default Warehouse</dt><dd class="mt-1 text-gray-800 dark:text-gray-100">{{ $route->warehouse?->warehouse_name ?: '—' }}</dd></div>
                        <div><dt class="text-gray-500 dark:text-gray-400">Assigned Technician</dt><dd class="mt-1 text-gray-800 dark:text-gray-100">{{ $route->assignedUser?->name ?: 'Unassigned' }}</dd></div>
                        <div><dt class="text-gray-500 dark:text-gray-400">Auto Schedule Services</dt><dd class="mt-1 text-gray-800 dark:text-gray-100">{{ $route->auto_schedule_enabled ? 'Enabled' : 'Disabled' }}</dd></div>
                        <div><dt class="text-gray-500 dark:text-gray-400">Description</dt><dd class="mt-1 text-gray-800 dark:text-gray-100">{{ $route->description ?: '—' }}</dd></div>
                    </dl>
                </div>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Add Location to Route</h2>
                    </div>
                </div>
                <div class="panel-body">
                    @if ($availableLocations->isEmpty())
                        <p class="text-sm text-gray-500 dark:text-gray-400">All current-account locations are already assigned to this route.</p>
                    @else
                        <form method="POST" action="{{ route('routes.locations.store', $route) }}" class="grid gap-4 md:grid-cols-[1fr_auto]">
                            @csrf
                            <select id="location_id" name="location_id" class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100" required>
                                <option value="">Select a location</option>
                                @foreach ($availableLocations as $location)
                                    <option value="{{ $location->id }}" @selected(old('location_id') == $location->id)>{{ $location->location_name }}{{ $location->city ? ' · '.$location->city : '' }}</option>
                                @endforeach
                            </select>
                            <x-button>Add Location</x-button>
                        </form>
                    @endif
                </div>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Ordered Stops</h2>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700/60">
                        <thead class="bg-gray-50 dark:bg-gray-800/80">
                            <tr>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Stop #</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Location Name</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Address</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">City</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">State</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700/60">
                            @forelse ($route->routeLocations as $routeLocation)
                                <tr class="bg-white dark:bg-gray-800">
                                    <td class="px-5 py-4 font-medium text-gray-800 dark:text-gray-100">{{ $routeLocation->stop_order }}</td>
                                    <td class="px-5 py-4 font-medium text-gray-800 dark:text-gray-100">{{ $routeLocation->location?->location_name ?? 'Unknown Location' }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $routeLocation->location?->address ?: '—' }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $routeLocation->location?->city ?: '—' }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $routeLocation->location?->state ?: '—' }}</td>
                                    <td class="px-5 py-4">
                                        <div class="flex flex-wrap gap-2">
                                            @if (! $loop->first)
                                                <form method="POST" action="{{ route('routes.locations.move-up', [$route, $routeLocation]) }}">
                                                    @csrf
                                                    <button type="submit" class="inline-flex items-center rounded-xl border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Move Up</button>
                                                </form>
                                            @endif
                                            @if (! $loop->last)
                                                <form method="POST" action="{{ route('routes.locations.move-down', [$route, $routeLocation]) }}">
                                                    @csrf
                                                    <button type="submit" class="inline-flex items-center rounded-xl border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Move Down</button>
                                                </form>
                                            @endif
                                            <form method="POST" action="{{ route('routes.locations.destroy', [$route, $routeLocation]) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="inline-flex items-center rounded-xl border border-red-300 px-3 py-1.5 text-xs font-medium text-red-700 transition hover:bg-red-50 dark:border-red-500/40 dark:text-red-300 dark:hover:bg-red-500/10">Remove From Route</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr class="bg-white dark:bg-gray-800">
                                    <td colspan="6" class="px-5 py-8 text-center text-gray-500 dark:text-gray-400">No locations are assigned to this route yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
