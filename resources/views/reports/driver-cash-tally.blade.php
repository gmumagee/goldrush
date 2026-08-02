<x-app-layout title="Driver Cash Tally">
    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-6xl space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 md:text-3xl">Driver Cash Tally</h1>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Cash collections grouped by the assigned driver on each location service, showing how much each person should be turning in.</p>
            </div>

            <x-validation-errors />

            <section class="panel">
                <div class="panel-body border-b border-gray-200 dark:border-gray-700/60">
                    <form method="GET" action="{{ route('reports.driver-cash-tally') }}" class="space-y-4">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="md:col-span-2">
                                <x-label for="driver_filter" value="Driver" />
                                <select id="driver_filter" name="driver_filter" class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100">
                                    <option value="">All Drivers</option>
                                    <option value="unassigned" @selected($filters['driver_filter'] === 'unassigned')>Unassigned</option>
                                    @foreach ($drivers as $driver)
                                        <option value="{{ $driver->id }}" @selected($filters['driver_filter'] === (string) $driver->id)>{{ $driver->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <x-label for="date_from" value="From" />
                                <x-input id="date_from" name="date_from" type="date" :value="$filters['date_from']" />
                            </div>

                            <div>
                                <x-label for="date_to" value="To" />
                                <x-input id="date_to" name="date_to" type="date" :value="$filters['date_to']" />
                            </div>
                        </div>

                        <div class="flex gap-3">
                            <x-button>Filter</x-button>
                            <a href="{{ route('reports.driver-cash-tally') }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Reset</a>
                        </div>
                    </form>
                </div>

                <div class="panel-body space-y-6">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">
                                {{ $selectedDriver['name'] ?? 'All Drivers' }}
                            </h2>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Service dates from {{ \App\Support\AppDateTime::displayDate($filters['date_from']) }} to {{ \App\Support\AppDateTime::displayDate($filters['date_to']) }}
                            </p>
                        </div>
                        <div class="rounded-2xl border border-violet-200 bg-violet-50 px-4 py-3 text-right dark:border-violet-500/30 dark:bg-violet-500/10">
                            <div class="text-xs font-medium uppercase tracking-wide text-violet-700 dark:text-violet-300">Grand Total</div>
                            <div class="mt-1 text-2xl font-semibold text-violet-800 dark:text-violet-200">{{ $grandTotal }}</div>
                        </div>
                    </div>

                    @if (! $hasData)
                        <div class="rounded-2xl border border-dashed border-gray-300 bg-gray-50 px-6 py-8 text-center text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900/30 dark:text-gray-400">
                            No collections in this date range.
                        </div>
                    @else
                        <div class="space-y-6">
                            @foreach ($groupedTallies as $group)
                                <section class="rounded-2xl border border-gray-200 bg-white dark:border-gray-700/60 dark:bg-gray-900/30">
                                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-4 dark:border-gray-700/60">
                                        <div>
                                            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100">{{ $group['driver_name'] }}</h3>
                                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $group['services']->count() }} collection {{ \Illuminate\Support\Str::plural('event', $group['services']->count()) }}</p>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Driver Subtotal</div>
                                            <div class="mt-1 text-xl font-semibold text-gray-900 dark:text-white">{{ $group['subtotal_display'] }}</div>
                                        </div>
                                    </div>

                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700/60">
                                            <thead class="bg-gray-50 dark:bg-gray-800/80">
                                                <tr>
                                                    <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Service Date</th>
                                                    <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Location</th>
                                                    <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Service</th>
                                                    <th class="px-5 py-3 text-right font-medium text-gray-500 dark:text-gray-400">Amount Collected</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700/60">
                                                @foreach ($group['services'] as $service)
                                                    <tr class="bg-white dark:bg-gray-800">
                                                        <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ \App\Support\AppDateTime::displayDate($service->service_date) }}</td>
                                                        <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $service->location?->location_name ?? 'Unknown Location' }}</td>
                                                        <td class="px-5 py-4">
                                                            <a href="{{ route('services.show', $service) }}" class="font-medium text-violet-700 transition hover:text-violet-600 dark:text-violet-300 dark:hover:text-violet-200">
                                                                Service #{{ $service->id }}
                                                            </a>
                                                        </td>
                                                        <td class="px-5 py-4 text-right tabular-nums text-gray-600 dark:text-gray-300">{{ \App\Support\Money::format($service->amount_collected) }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </section>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
