<x-app-layout title="Calendar Event">
    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-6xl space-y-6">
            @php
                $statusKey = strtolower(trim((string) $event->status));
                $typeKey = strtolower(trim((string) $event->event_type));
                $priorityKey = strtolower(trim((string) $event->priority));
                // Precompute date and time labels so the event summary stays aligned with the shared formatter.
                $eventStartDate = \App\Support\AppDateTime::displayDate($event->start_at);
                $eventStartTime = $event->all_day
                    ? 'All day'
                    : \App\Support\AppDateTime::displayTime($event->start_at);
                $eventEndSource = $event->end_at ?: $event->start_at;
                $eventEndDate = \App\Support\AppDateTime::displayDate($eventEndSource);
                $eventEndTime = $event->all_day
                    ? 'All day'
                    : \App\Support\AppDateTime::displayTime($event->end_at);
                $statusClasses = match ($statusKey) {
                    strtolower(\App\Models\CalendarEvent::STATUS_SCHEDULED) => 'bg-blue-100 text-blue-800 dark:bg-blue-500/15 dark:text-blue-300',
                    strtolower(\App\Models\CalendarEvent::STATUS_COMPLETED) => 'bg-green-100 text-green-800 dark:bg-green-500/15 dark:text-green-300',
                    strtolower(\App\Models\CalendarEvent::STATUS_CANCELLED) => 'bg-red-100 text-red-800 dark:bg-red-500/15 dark:text-red-300',
                    default => 'bg-gray-100 text-gray-700 dark:bg-gray-700/60 dark:text-gray-200',
                };
            @endphp

            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 md:text-3xl">{{ $event->title }}</h1>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ $eventTypeLabels[$typeKey] ?? ($event->event_type ?: 'Event') }}</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <a href="{{ route('calendar-events.index') }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Back to Calendar</a>
                    <a href="{{ route('calendar-events.edit', $event) }}" class="inline-flex items-center rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-violet-500">Edit Event</a>
                    @if ($event->isScheduled())
                        <form method="POST" action="{{ route('calendar-events.complete', $event) }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center rounded-xl bg-green-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-green-500">Complete Event</button>
                        </form>
                        <form method="POST" action="{{ route('calendar-events.cancel', $event) }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center rounded-xl border border-red-300 px-4 py-2 text-sm font-medium text-red-700 transition hover:bg-red-50 dark:border-red-500/40 dark:text-red-300 dark:hover:bg-red-500/10">Cancel Event</button>
                        </form>
                    @endif
                </div>
            </div>

            @if (session('status'))
                <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-900/60 dark:bg-green-500/10 dark:text-green-300">{{ session('status') }}</div>
            @endif

            <x-validation-errors />

            <section class="panel">
                <div class="panel-body">
                    <dl class="grid gap-4 text-sm md:grid-cols-2 xl:grid-cols-4">
                        <div><dt class="text-gray-500 dark:text-gray-400">Status</dt><dd class="mt-1"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $statusClasses }}">{{ $eventStatusLabels[$statusKey] ?? ($event->status ?: 'Unknown') }}</span></dd></div>
                        <div><dt class="text-gray-500 dark:text-gray-400">Priority</dt><dd class="mt-1 text-gray-800 dark:text-gray-100">{{ $eventPriorityLabels[$priorityKey] ?? ($event->priority ?: '—') }}</dd></div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Start</dt>
                            <dd class="mt-1 text-gray-800 dark:text-gray-100">
                                <div>{{ $eventStartDate }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $eventStartTime }}</div>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">End</dt>
                            <dd class="mt-1 text-gray-800 dark:text-gray-100">
                                <div>{{ $eventEndDate }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $eventEndTime }}</div>
                            </dd>
                        </div>
                        <div><dt class="text-gray-500 dark:text-gray-400">Assigned User</dt><dd class="mt-1 text-gray-800 dark:text-gray-100">{{ $event->assignedUser?->name ?? '—' }}</dd></div>
                        <div><dt class="text-gray-500 dark:text-gray-400">Location</dt><dd class="mt-1 text-gray-800 dark:text-gray-100">{{ $event->location?->location_name ?? '—' }}</dd></div>
                        <div><dt class="text-gray-500 dark:text-gray-400">Warehouse</dt><dd class="mt-1 text-gray-800 dark:text-gray-100">{{ $event->warehouse?->warehouse_name ?? '—' }}</dd></div>
                        <div><dt class="text-gray-500 dark:text-gray-400">Route</dt><dd class="mt-1 text-gray-800 dark:text-gray-100">{{ $event->route?->route_name ?? '—' }}</dd></div>
                        <div class="md:col-span-2 xl:col-span-4"><dt class="text-gray-500 dark:text-gray-400">Description</dt><dd class="mt-1 whitespace-pre-line text-gray-800 dark:text-gray-100">{{ $event->description ?: '—' }}</dd></div>
                    </dl>
                </div>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Related Record</h2>
                    </div>
                </div>
                <div class="panel-body">
                    @if ($event->sourceRecord() && $event->sourceRouteName())
                        <div class="flex flex-wrap items-center gap-4 text-sm text-gray-700 dark:text-gray-200">
                            <div>
                                <span class="text-gray-500 dark:text-gray-400">Source Type:</span>
                                <span class="ml-1">{{ ucfirst((string) $event->source_type) }}</span>
                            </div>
                            <div>
                                <span class="text-gray-500 dark:text-gray-400">Source ID:</span>
                                <span class="ml-1">#{{ $event->source_id }}</span>
                            </div>
                            <a href="{{ route($event->sourceRouteName(), $event->sourceRecord()) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                                {{ $event->sourceLinkLabel() ?? 'Open Related Record' }}
                            </a>
                        </div>
                    @else
                        <p class="text-sm text-gray-500 dark:text-gray-400">This calendar event is not linked to a related record.</p>
                    @endif
                </div>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Reminders</h2>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700/60">
                        <thead class="bg-gray-50 dark:bg-gray-800/80">
                            <tr>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Remind At</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Type</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Status</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Assigned User</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Message</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700/60">
                            @forelse ($event->reminders as $reminder)
                                @php
                                    $reminderStatusKey = strtolower(trim((string) $reminder->status));
                                @endphp
                                <tr class="bg-white dark:bg-gray-800">
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">
                                        {{-- Split reminder date and time so the reminder grid stays consistent with AGENT display rules. --}}
                                        <div>{{ \App\Support\AppDateTime::displayDate($reminder->remind_at) }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ \App\Support\AppDateTime::displayTime($reminder->remind_at) }}</div>
                                    </td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $reminder->reminder_type }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $reminderStatusLabels[$reminderStatusKey] ?? ($reminder->status ?: 'Unknown') }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $reminder->assignedUser?->name ?? '—' }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $reminder->message ?: $event->title }}</td>
                                    <td class="px-5 py-4">
                                        @if ($reminder->status === \App\Models\CalendarReminder::STATUS_PENDING)
                                            <form method="POST" action="{{ route('calendar-reminders.dismiss', $reminder) }}">
                                                @csrf
                                                <button type="submit" class="inline-flex items-center rounded-xl border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Dismiss</button>
                                            </form>
                                        @else
                                            <span class="text-xs text-gray-500 dark:text-gray-400">Dismissed</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr class="bg-white dark:bg-gray-800">
                                    <td colspan="6" class="px-5 py-8 text-center text-gray-500 dark:text-gray-400">No reminders are configured for this event.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
