<x-app-layout title="Services">
    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-7xl space-y-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 md:text-3xl">Services</h1>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Manage location-based vending service visits for the selected account.</p>
                </div>

                <a href="{{ route('services.create') }}" class="inline-flex items-center rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-violet-500">
                    Create Service
                </a>
            </div>

            @if (session('status'))
                <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-900/60 dark:bg-green-500/10 dark:text-green-300">
                    {{ session('status') }}
                </div>
            @endif

            <x-validation-errors />

            @php
                $statusClasses = static function (?string $status): string {
                    return match (strtolower(trim((string) $status))) {
                        strtolower(\App\Models\Service::STATUS_AWAITING_SERVICE) => 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300',
                        strtolower(\App\Models\Service::STATUS_SERVICE_OPEN) => 'bg-blue-100 text-blue-800 dark:bg-blue-500/15 dark:text-blue-300',
                        strtolower(\App\Models\Service::STATUS_SERVICE_COMPLETED) => 'bg-violet-100 text-violet-800 dark:bg-violet-500/15 dark:text-violet-300',
                        strtolower(\App\Models\Service::STATUS_SERVICE_CLOSED) => 'bg-green-100 text-green-800 dark:bg-green-500/15 dark:text-green-300',
                        default => 'bg-gray-100 text-gray-700 dark:bg-gray-700/60 dark:text-gray-200',
                    };
                };

                $displayStatus = static function (?string $status) use ($serviceStatusLabels): string {
                    $normalizedStatus = strtolower(trim((string) $status));

                    return $serviceStatusLabels[$normalizedStatus] ?? ($status ?: 'Unknown');
                };
            @endphp

            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Pending Services</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Services awaiting work, grouped by location.</p>
                    </div>
                    <div class="rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-800 dark:bg-amber-500/15 dark:text-amber-300">
                        {{ $pendingServicesCount }} total
                    </div>
                </div>

                <div class="panel-body space-y-3">
                    @forelse ($pendingServicesByLocation as $locationId => $services)
                        @php
                            $location = $services->first()?->location;
                            $locationName = $location?->location_name ?? 'Unknown Location';
                        @endphp

                        <div x-data="{ open: false }" class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700/60">
                            <button
                                type="button"
                                class="flex w-full items-center justify-between gap-4 bg-gray-50 px-4 py-3 text-left dark:bg-gray-800/80"
                                @click="open = !open"
                                :aria-expanded="open.toString()"
                            >
                                <div class="min-w-0">
                                    <div class="font-medium text-gray-800 dark:text-gray-100">{{ $locationName }}</div>
                                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        {{ $services->count() }} pending {{ \Illuminate\Support\Str::plural('service', $services->count()) }}
                                    </div>
                                </div>
                                <span class="inline-flex h-4 w-4 shrink-0 items-center justify-center text-sm leading-none text-gray-400 transition-transform duration-200" :class="open ? 'rotate-90' : ''" aria-hidden="true">›</span>
                            </button>

                            <div x-show="open" x-transition.origin.top.duration.200ms>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700/60">
                                        <thead class="bg-white dark:bg-gray-800">
                                            <tr>
                                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Service ID</th>
                                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Service Date</th>
                                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Assigned User</th>
                                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Status</th>
                                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700/60">
                                            @foreach ($services as $service)
                                                <tr class="bg-white dark:bg-gray-800">
                                                    <td class="px-5 py-4 font-medium text-gray-800 dark:text-gray-100">#{{ $service->id }}</td>
                                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ \App\Support\AppDateTime::displayDate($service->service_date) }}</td>
                                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $service->user?->name ?? '—' }}</td>
                                                    <td class="px-5 py-4">
                                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $statusClasses($service->status) }}">
                                                            {{ $displayStatus($service->status) }}
                                                        </span>
                                                    </td>
                                                    <td class="px-5 py-4">
                                                        <div class="flex flex-wrap items-center gap-2">
                                                            <a href="{{ route('services.show', $service) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                                                                View
                                                            </a>
                                                            <a href="{{ route('services.edit', $service) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                                                                Edit
                                                            </a>
                                                            <form method="POST" action="{{ route('services.open', $service) }}">
                                                                @csrf
                                                                <button type="submit" class="inline-flex items-center rounded-xl border border-blue-300 px-3 py-1.5 text-xs font-medium text-blue-700 transition hover:bg-blue-50 dark:border-blue-500/40 dark:text-blue-300 dark:hover:bg-blue-500/10">
                                                                    Open Service
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-xl border border-dashed border-gray-300 px-4 py-6 text-sm text-gray-500 dark:border-gray-700/60 dark:text-gray-400">
                            No pending services.
                        </div>
                    @endforelse
                </div>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Completed Services Awaiting Money Entry</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Completed services that still need the collected amount entered.</p>
                    </div>
                    <div class="rounded-full bg-violet-100 px-3 py-1 text-xs font-medium text-violet-800 dark:bg-violet-500/15 dark:text-violet-300">
                        {{ $completedServicesCount }} total
                    </div>
                </div>

                <div class="panel-body space-y-3">
                    @forelse ($completedServicesByLocation as $locationId => $services)
                        @php
                            $location = $services->first()?->location;
                            $locationName = $location?->location_name ?? 'Unknown Location';
                        @endphp

                        <div x-data="{ open: false }" class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700/60">
                            <button
                                type="button"
                                class="flex w-full items-center justify-between gap-4 bg-gray-50 px-4 py-3 text-left dark:bg-gray-800/80"
                                @click="open = !open"
                                :aria-expanded="open.toString()"
                            >
                                <div class="min-w-0">
                                    <div class="font-medium text-gray-800 dark:text-gray-100">{{ $locationName }}</div>
                                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        {{ $services->count() }} {{ \Illuminate\Support\Str::plural('service', $services->count()) }}
                                    </div>
                                </div>
                                <span class="inline-flex h-4 w-4 shrink-0 items-center justify-center text-sm leading-none text-gray-400 transition-transform duration-200" :class="open ? 'rotate-90' : ''" aria-hidden="true">›</span>
                            </button>

                            <div x-show="open" x-transition.origin.top.duration.200ms>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700/60">
                                        <thead class="bg-white dark:bg-gray-800">
                                            <tr>
                                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Service ID</th>
                                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Service Date</th>
                                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Assigned User</th>
                                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Status</th>
                                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Completed At</th>
                                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Amount Collected</th>
                                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700/60">
                                            @foreach ($services as $service)
                                                <tr class="bg-white dark:bg-gray-800">
                                                    <td class="px-5 py-4 font-medium text-gray-800 dark:text-gray-100">#{{ $service->id }}</td>
                                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ \App\Support\AppDateTime::displayDate($service->service_date) }}</td>
                                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $service->user?->name ?? '—' }}</td>
                                                    <td class="px-5 py-4">
                                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $statusClasses($service->status) }}">
                                                            {{ $displayStatus($service->status) }}
                                                        </span>
                                                    </td>
                                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ \App\Support\AppDateTime::displayTime($service->completed_at) }}</td>
                                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">Pending</td>
                                                    <td class="px-5 py-4">
                                                        <div class="flex flex-wrap items-center gap-2">
                                                            <a href="{{ route('services.show', $service) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                                                                View
                                                            </a>
                                                            <a href="{{ route('services.amount-collected.edit', $service) }}" class="inline-flex items-center rounded-xl border border-violet-300 px-3 py-1.5 text-xs font-medium text-violet-700 transition hover:bg-violet-50 dark:border-violet-500/40 dark:text-violet-300 dark:hover:bg-violet-500/10">
                                                                Enter Amount Collected
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-xl border border-dashed border-gray-300 px-4 py-6 text-sm text-gray-500 dark:border-gray-700/60 dark:text-gray-400">
                            No completed services are awaiting money entry.
                        </div>
                    @endforelse
                </div>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">All Services</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">All services grouped by location.</p>
                    </div>
                    <div class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700 dark:bg-gray-700/60 dark:text-gray-200">
                        {{ $allServicesCount }} total
                    </div>
                </div>

                <div class="panel-body space-y-3">
                    @forelse ($allServicesByLocation as $locationId => $services)
                        @php
                            $location = $services->first()?->location;
                            $locationName = $location?->location_name ?? 'Unknown Location';
                        @endphp

                        <div x-data="{ open: false }" class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700/60">
                            <button
                                type="button"
                                class="flex w-full items-center justify-between gap-4 bg-gray-50 px-4 py-3 text-left dark:bg-gray-800/80"
                                @click="open = !open"
                                :aria-expanded="open.toString()"
                            >
                                <div class="min-w-0">
                                    <div class="font-medium text-gray-800 dark:text-gray-100">{{ $locationName }}</div>
                                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        {{ $services->count() }} {{ \Illuminate\Support\Str::plural('service', $services->count()) }}
                                    </div>
                                </div>
                                <span class="inline-flex h-4 w-4 shrink-0 items-center justify-center text-sm leading-none text-gray-400 transition-transform duration-200" :class="open ? 'rotate-90' : ''" aria-hidden="true">›</span>
                            </button>

                            <div x-show="open" x-transition.origin.top.duration.200ms>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700/60">
                                        <thead class="bg-white dark:bg-gray-800">
                                            <tr>
                                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Service ID</th>
                                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Service Date</th>
                                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Assigned User</th>
                                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Status</th>
                                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Opened At</th>
                                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Completed At</th>
                                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Closed At</th>
                                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Closed By</th>
                                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Amount Collected</th>
                                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700/60">
                                            @foreach ($services as $service)
                                                <tr class="bg-white dark:bg-gray-800">
                                                    <td class="px-5 py-4 font-medium text-gray-800 dark:text-gray-100">#{{ $service->id }}</td>
                                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ \App\Support\AppDateTime::displayDate($service->service_date) }}</td>
                                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $service->user?->name ?? '—' }}</td>
                                                    <td class="px-5 py-4">
                                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $statusClasses($service->status) }}">
                                                            {{ $displayStatus($service->status) }}
                                                        </span>
                                                    </td>
                                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ \App\Support\AppDateTime::displayTime($service->opened_at) }}</td>
                                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ \App\Support\AppDateTime::displayTime($service->completed_at) }}</td>
                                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ \App\Support\AppDateTime::displayTime($service->closed_at) }}</td>
                                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $service->closedBy?->name ?? 'Not closed yet' }}</td>
                                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">
                                                        @if ($service->amount_collected !== null)
                                                            {{ number_format((float) $service->amount_collected, 2) }}
                                                        @elseif ($service->isServiceCompleted())
                                                            Pending
                                                        @else
                                                            —
                                                        @endif
                                                    </td>
                                                    <td class="px-5 py-4">
                                                        <div class="flex flex-wrap items-center gap-2">
                                                            <a href="{{ route('services.show', $service) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                                                                View
                                                            </a>

                                                            @if ($service->isAwaitingService())
                                                                <form method="POST" action="{{ route('services.open', $service) }}">
                                                                    @csrf
                                                                    <button type="submit" class="inline-flex items-center rounded-xl border border-blue-300 px-3 py-1.5 text-xs font-medium text-blue-700 transition hover:bg-blue-50 dark:border-blue-500/40 dark:text-blue-300 dark:hover:bg-blue-500/10">
                                                                        Open Service
                                                                    </button>
                                                                </form>
                                                            @endif

                                                            @if ($service->isServiceOpen())
                                                                <form method="POST" action="{{ route('services.complete', $service) }}">
                                                                    @csrf
                                                                    <button type="submit" class="inline-flex items-center rounded-xl border border-green-300 px-3 py-1.5 text-xs font-medium text-green-700 transition hover:bg-green-50 dark:border-green-500/40 dark:text-green-300 dark:hover:bg-green-500/10">
                                                                        Complete Service
                                                                    </button>
                                                                </form>
                                                            @endif

                                                            @if ($service->isServiceCompleted() && $service->amount_collected === null)
                                                                <a href="{{ route('services.amount-collected.edit', $service) }}" class="inline-flex items-center rounded-xl border border-violet-300 px-3 py-1.5 text-xs font-medium text-violet-700 transition hover:bg-violet-50 dark:border-violet-500/40 dark:text-violet-300 dark:hover:bg-violet-500/10">
                                                                    Enter Amount Collected
                                                                </a>
                                                            @endif
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-xl border border-dashed border-gray-300 px-4 py-6 text-sm text-gray-500 dark:border-gray-700/60 dark:text-gray-400">
                            No services found for this account.
                        </div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
