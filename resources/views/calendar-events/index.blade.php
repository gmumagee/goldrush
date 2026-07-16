<x-app-layout title="Calendar">
    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-7xl space-y-6">
            @php
                $statusClasses = static function (?string $status): string {
                    return match (strtolower(trim((string) $status))) {
                        strtolower(\App\Models\CalendarEvent::STATUS_SCHEDULED) => 'bg-blue-100 text-blue-800 dark:bg-blue-500/15 dark:text-blue-300',
                        strtolower(\App\Models\CalendarEvent::STATUS_COMPLETED) => 'bg-green-100 text-green-800 dark:bg-green-500/15 dark:text-green-300',
                        strtolower(\App\Models\CalendarEvent::STATUS_CANCELLED) => 'bg-red-100 text-red-800 dark:bg-red-500/15 dark:text-red-300',
                        default => 'bg-gray-100 text-gray-700 dark:bg-gray-700/60 dark:text-gray-200',
                    };
                };
            @endphp

            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 md:text-3xl">Calendar</h1>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Scheduled account events in date order for the current account.</p>
                </div>
                <a href="{{ route('calendar-events.create') }}" class="inline-flex items-center rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-violet-500">Create Event</a>
            </div>

            @if (session('status'))
                <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-900/60 dark:bg-green-500/10 dark:text-green-300">{{ session('status') }}</div>
            @endif

            <x-validation-errors />

            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Filters</h2>
                    </div>
                </div>
                <div class="panel-body">
                    <form method="GET" action="{{ route('calendar-events.index') }}" class="grid gap-4 lg:grid-cols-5">
                        <div>
                            <x-label for="start_date_from" value="Date From" />
                            <x-input id="start_date_from" name="start_date_from" type="text" placeholder="DD-MM-YYYY" :value="\App\Support\AppDateTime::inputDate($filters['start_date_from'] ?? null)" />
                        </div>
                        <div>
                            <x-label for="start_date_to" value="Date To" />
                            <x-input id="start_date_to" name="start_date_to" type="text" placeholder="DD-MM-YYYY" :value="\App\Support\AppDateTime::inputDate($filters['start_date_to'] ?? null)" />
                        </div>
                        <div>
                            <x-label for="event_type" value="Event Type" />
                            <select id="event_type" name="event_type" class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100">
                                <option value="">All types</option>
                                @foreach ($eventTypeOptions as $option)
                                    <option value="{{ $option->value }}" @selected(($filters['event_type'] ?? '') === $option->value)>{{ $option->displayLabel() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-label for="status" value="Status" />
                            <select id="status" name="status" class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100">
                                <option value="">All statuses</option>
                                @foreach ($eventStatusOptions as $option)
                                    <option value="{{ $option->value }}" @selected(($filters['status'] ?? '') === $option->value)>{{ $option->displayLabel() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-label for="assigned_user_id" value="Assigned User" />
                            <select id="assigned_user_id" name="assigned_user_id" class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100">
                                <option value="">All users</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}" @selected((int) ($filters['assigned_user_id'] ?? 0) === $user->id)>{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="lg:col-span-2">
                            <x-label for="location_id" value="Location" />
                            <select id="location_id" name="location_id" class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100">
                                <option value="">All locations</option>
                                @foreach ($locations as $location)
                                    <option value="{{ $location->id }}" @selected((int) ($filters['location_id'] ?? 0) === $location->id)>{{ $location->location_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex items-end gap-3 lg:col-span-3">
                            <x-button>Apply Filters</x-button>
                            <a href="{{ route('calendar-events.index') }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Reset</a>
                        </div>
                    </form>
                </div>
            </section>

            <section class="panel">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700/60">
                        <thead class="bg-gray-50 dark:bg-gray-800/80">
                            <tr>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Date/Time</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Type</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Title</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Location</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Warehouse</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Route</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Assigned User</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Status</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Priority</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700/60">
                            @forelse ($events as $event)
                                @php
                                    $statusKey = strtolower(trim((string) $event->status));
                                    $typeKey = strtolower(trim((string) $event->event_type));
                                    $priorityKey = strtolower(trim((string) $event->priority));
                                @endphp
                                <tr class="bg-white dark:bg-gray-800">
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">
                                        <div>{{ $event->start_at ? $event->start_at->format('d-m-Y H:i:s') : '—' }}</div>
                                        @if ($event->is_overdue)
                                            <div class="mt-1 text-xs text-red-600 dark:text-red-300">Overdue</div>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $eventTypeLabels[$typeKey] ?? ($event->event_type ?: '—') }}</td>
                                    <td class="px-5 py-4 font-medium text-gray-800 dark:text-gray-100">{{ $event->title }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $event->location?->location_name ?? '—' }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $event->warehouse?->warehouse_name ?? '—' }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $event->route?->route_name ?? '—' }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $event->assignedUser?->name ?? '—' }}</td>
                                    <td class="px-5 py-4">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $statusClasses($event->status) }}">{{ $eventStatusLabels[$statusKey] ?? ($event->status ?: 'Unknown') }}</span>
                                    </td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $eventPriorityLabels[$priorityKey] ?? ($event->priority ?: '—') }}</td>
                                    <td class="px-5 py-4">
                                        <div class="flex flex-wrap gap-2">
                                            <a href="{{ route('calendar-events.show', $event) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">View</a>
                                            <a href="{{ route('calendar-events.edit', $event) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Edit</a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr class="bg-white dark:bg-gray-800">
                                    <td colspan="10" class="px-5 py-8 text-center text-gray-500 dark:text-gray-400">No calendar events match the current filters.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="panel-body">{{ $events->links() }}</div>
            </section>
        </div>
    </div>
</x-app-layout>
