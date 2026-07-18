<x-app-layout title="Calendar">
    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-7xl space-y-6">
            @php
                $queryWithoutDate = request()->except('date');
            @endphp

            <style>
                .calendar-filter-grid {
                    display: grid;
                    grid-template-columns: repeat(4, minmax(0, 1fr));
                    gap: 0.75rem;
                    align-items: end;
                }

                .calendar-filter-field {
                    min-width: 0;
                }

                .calendar-filter-field label {
                    display: block;
                    margin-bottom: 0.25rem;
                    font-size: 0.75rem;
                    font-weight: 600;
                    text-transform: uppercase;
                    letter-spacing: 0.04em;
                    color: rgb(75 85 99);
                }

                .dark .calendar-filter-field label {
                    color: rgb(209 213 219);
                }

                .calendar-filter-field input,
                .calendar-filter-field select {
                    width: 100%;
                    min-width: 0;
                    border-radius: 0.5rem;
                    border: 1px solid rgb(209 213 219);
                    background: rgb(255 255 255);
                    padding: 0.5rem 0.75rem;
                    font-size: 0.875rem;
                    line-height: 1.25rem;
                    color: rgb(31 41 55);
                    box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
                }

                .calendar-filter-field input::placeholder {
                    color: rgb(156 163 175);
                }

                .calendar-filter-field input:focus,
                .calendar-filter-field select:focus {
                    outline: none;
                    border-color: rgb(139 92 246);
                    box-shadow: 0 0 0 1px rgb(139 92 246);
                }

                .dark .calendar-filter-field input,
                .dark .calendar-filter-field select {
                    border-color: rgb(55 65 81);
                    background: rgb(31 41 55);
                    color: rgb(243 244 246);
                }

                .dark .calendar-filter-field input::placeholder {
                    color: rgb(107 114 128);
                }

                .calendar-filter-actions {
                    display: flex;
                    align-items: end;
                    gap: 0.5rem;
                    min-width: 0;
                }

                .calendar-filter-actions > * {
                    white-space: nowrap;
                }

                @media (max-width: 992px) {
                    .calendar-filter-grid {
                        grid-template-columns: repeat(2, minmax(0, 1fr));
                    }
                }

                @media (max-width: 576px) {
                    .calendar-filter-grid {
                        grid-template-columns: 1fr;
                    }

                    .calendar-filter-actions {
                        flex-wrap: wrap;
                    }
                }
            </style>

            <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 md:text-3xl">Calendar</h1>
                </div>
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
                <div class="panel-body py-3">
                    <form method="GET" action="{{ route('calendar-events.index') }}">
                        <div class="calendar-filter-grid">
                            <div class="calendar-filter-field">
                                <label for="date">Week Of</label>
                                <input id="date" name="date" type="date" value="{{ ($filters['date'] ?? now())->toDateString() }}">
                            </div>

                            <div class="calendar-filter-field">
                                <label for="event_type">Event Type</label>
                                <select id="event_type" name="event_type">
                                    <option value="">All types</option>
                                    @foreach ($eventTypeOptions as $option)
                                        <option value="{{ $option->value }}" @selected(($filters['event_type'] ?? '') === $option->value)>{{ $option->displayLabel() }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="calendar-filter-field">
                                <label for="status">Status</label>
                                <select id="status" name="status">
                                    <option value="{{ \App\Models\CalendarEvent::STATUS_SCHEDULED }}" @selected(($filters['status'] ?? \App\Models\CalendarEvent::STATUS_SCHEDULED) === \App\Models\CalendarEvent::STATUS_SCHEDULED)>Scheduled</option>
                                    <option value="{{ \App\Models\CalendarEvent::STATUS_COMPLETED }}" @selected(($filters['status'] ?? '') === \App\Models\CalendarEvent::STATUS_COMPLETED)>Completed</option>
                                    <option value="{{ \App\Models\CalendarEvent::STATUS_CANCELLED }}" @selected(($filters['status'] ?? '') === \App\Models\CalendarEvent::STATUS_CANCELLED)>Cancelled</option>
                                    <option value="all" @selected(($filters['status'] ?? '') === 'all')>All</option>
                                </select>
                            </div>

                            <div class="calendar-filter-field">
                                <label for="assigned_user_id">Assigned User</label>
                                <select id="assigned_user_id" name="assigned_user_id">
                                    <option value="">All users</option>
                                    @foreach ($users as $user)
                                        <option value="{{ $user->id }}" @selected((int) ($filters['assigned_user_id'] ?? 0) === $user->id)>{{ $user->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="calendar-filter-field">
                                <label for="location_id">Location</label>
                                <select id="location_id" name="location_id">
                                    <option value="">All locations</option>
                                    @foreach ($locations as $location)
                                        <option value="{{ $location->id }}" @selected((int) ($filters['location_id'] ?? 0) === $location->id)>{{ $location->location_name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="calendar-filter-field">
                                <label for="search">Search</label>
                                <input id="search" name="search" type="text" value="{{ $filters['search'] ?? '' }}" placeholder="Title, user, location...">
                            </div>

                            <div class="calendar-filter-actions">
                                <button type="submit" class="inline-flex items-center rounded-lg bg-violet-600 px-3 py-2 text-sm font-medium text-white transition hover:bg-violet-500">Apply</button>
                                <a href="{{ route('calendar-events.index') }}" class="inline-flex items-center rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Clear</a>
                            </div>
                        </div>
                    </form>
                </div>
            </section>

            @include('calendar-events._week', [
                'title' => 'Weekly Calendar',
                'weekStart' => $weekStart,
                'weekEnd' => $weekEnd,
                'weekDays' => $weekDays,
                'eventsByDate' => $eventsByDate,
                'previousWeekUrl' => route('calendar-events.index', array_merge($queryWithoutDate, ['date' => $weekStart->copy()->subWeek()->toDateString()])),
                'currentWeekUrl' => route('calendar-events.index', array_merge($queryWithoutDate, ['date' => now()->toDateString()])),
                'nextWeekUrl' => route('calendar-events.index', array_merge($queryWithoutDate, ['date' => $weekStart->copy()->addWeek()->toDateString()])),
                'createEventUrl' => route('calendar-events.create'),
                'emptyDayText' => ($filters['status'] ?? \App\Models\CalendarEvent::STATUS_SCHEDULED) === \App\Models\CalendarEvent::STATUS_SCHEDULED ? 'No scheduled events.' : 'No events.',
            ])
        </div>
    </div>
</x-app-layout>
