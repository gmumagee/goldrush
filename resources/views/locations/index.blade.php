<x-app-layout title="Locations">
    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-7xl space-y-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 md:text-3xl">Locations</h1>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Manage locations for the selected account.</p>
                </div>
                @can('create', \App\Models\Location::class)
                    <a href="{{ route('locations.create') }}" class="inline-flex items-center rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-violet-500">Add Location</a>
                @endcan
            </div>

            @if (session('status'))
                <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-900/60 dark:bg-green-500/10 dark:text-green-300">
                    {{ session('status') }}
                </div>
            @endif

            <x-validation-errors />

            <section class="panel">
                <div class="panel-body border-b border-gray-200 dark:border-gray-700/60">
                    <form method="GET" action="{{ route('locations.index') }}" class="grid gap-4 md:grid-cols-[1fr_auto]">
                        <x-input name="search" type="text" :value="$search" placeholder="Search location, city, or contact" />
                        <div class="flex gap-3">
                            <x-button>Search</x-button>
                            <a href="{{ route('locations.index') }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Reset</a>
                        </div>
                    </form>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700/60">
                        <thead class="bg-gray-50 dark:bg-gray-800/80">
                            <tr>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Location Name</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Route</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Address</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">City</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">State</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Contact</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700/60">
                            @forelse ($locations as $location)
                                <tr class="bg-white dark:bg-gray-800">
                                    <td class="px-5 py-4 font-medium text-gray-800 dark:text-gray-100">
                                        <a href="{{ route('locations.show', $location) }}" class="font-semibold text-violet-700 no-underline transition hover:text-violet-600 dark:text-violet-300 dark:hover:text-violet-200">
                                            {{ $location->location_name }}
                                        </a>
                                    </td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $location->primaryRouteLocation?->route?->route_name ?? '—' }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $location->address ?: '—' }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $location->city ?: '—' }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $location->state ?: '—' }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $location->primaryLocationContact?->contact?->display_name ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr class="bg-white dark:bg-gray-800">
                                    <td colspan="6" class="px-5 py-8 text-center text-gray-500 dark:text-gray-400">No locations found for this account.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="panel-body">
                    {{ $locations->links() }}
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
