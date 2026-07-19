@php
    $calendarTitle = $title ?? 'Weekly Calendar';
    $emptyDayText = $emptyDayText ?? 'No scheduled events.';
@endphp

<section class="weekly-calendar-card" aria-label="{{ $calendarTitle }}">
    <div class="weekly-calendar-header">
        <div>
            <h2 class="weekly-calendar-title">{{ $calendarTitle }}</h2>
            <div class="weekly-calendar-range">{{ $weekStart->format('F j, Y') }} - {{ $weekEnd->format('F j, Y') }}</div>
        </div>
        <div class="weekly-calendar-navigation">
            <a href="{{ $previousWeekUrl }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Previous Week</a>
            <a href="{{ $currentWeekUrl }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Current Week</a>
            <a href="{{ $nextWeekUrl }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Next Week</a>
            @isset($createEventUrl)
                <a href="{{ $createEventUrl }}" class="inline-flex items-center rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-violet-500">Create Event</a>
            @endisset
        </div>
    </div>
    <div class="weekly-calendar-body">
        <div class="weekly-calendar-days">
            @foreach ($weekDays as $day)
                @php
                    $dateKey = $day->toDateString();
                    $dayEvents = $eventsByDate->get($dateKey, collect());
                @endphp
                <section class="weekly-day-card" aria-labelledby="weekly-day-{{ $dateKey }}">
                    <div class="weekly-day-label">
                        <h3 id="weekly-day-{{ $dateKey }}" class="weekly-day-name">{{ $day->format('l') }}</h3>
                        <div class="weekly-day-date">{{ $day->format('M j') }}</div>
                    </div>
                    <div class="weekly-day-events">
                        @forelse ($dayEvents as $event)
                            <a href="{{ route('calendar-events.show', $event) }}" class="weekly-event-card {{ $event->calendar_color_class }}">
                                <div class="weekly-event-content">
                                    <div class="weekly-event-title-row">
                                        <span class="weekly-event-dot" aria-hidden="true"></span>
                                        <span class="weekly-event-title">{{ $event->title }}</span>
                                    </div>
                                    <div class="weekly-event-meta">
                                        @if ($event->all_day)
                                            <span>All day</span>
                                        @else
                                            <span>
                                                {{ $event->start_at?->format('g:i A') ?? 'No time' }}
                                                @if ($event->end_at)
                                                    - {{ $event->end_at->format('g:i A') }}
                                                @endif
                                            </span>
                                        @endif

                                        @if ($event->assignedUser)
                                            <span class="weekly-event-meta-divider" aria-hidden="true">•</span>
                                            <span>{{ $event->assignedUser->name }}</span>
                                        @endif
                                    </div>
                                </div>
                                <span class="weekly-event-arrow" aria-hidden="true">›</span>
                            </a>
                        @empty
                            <div class="weekly-calendar-empty">{{ $emptyDayText }}</div>
                        @endforelse
                    </div>
                </section>
            @endforeach
        </div>
    </div>
</section>
