@php
    $calendarTitle = $title ?? 'Weekly Calendar';
    $emptyDayText = $emptyDayText ?? 'No scheduled events.';
@endphp

<section class="panel">
    <div class="panel-header">
        <div>
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">{{ $calendarTitle }}</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $weekStart->format('F j, Y') }} - {{ $weekEnd->format('F j, Y') }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ $previousWeekUrl }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Previous Week</a>
            <a href="{{ $currentWeekUrl }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Current Week</a>
            <a href="{{ $nextWeekUrl }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Next Week</a>
            @isset($createEventUrl)
                <a href="{{ $createEventUrl }}" class="inline-flex items-center rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-violet-500">Create Event</a>
            @endisset
        </div>
    </div>
    <div class="panel-body">
        <div class="calendar-week-grid">
            @foreach ($weekDays as $day)
                @php
                    $dateKey = $day->toDateString();
                    $dayEvents = $eventsByDate->get($dateKey, collect());
                @endphp
                <article class="calendar-day-card">
                    <div class="calendar-day-header">
                        <div class="calendar-day-name">{{ $day->format('l') }}</div>
                        <div class="calendar-day-date">{{ $day->format('M j') }}</div>
                    </div>
                    <div class="calendar-day-events">
                        @forelse ($dayEvents as $event)
                            <div class="calendar-event-card {{ $event->calendar_color_class }}">
                                <div class="calendar-event-title">
                                    <a href="{{ route('calendar-events.show', $event) }}">{{ $event->title }}</a>
                                </div>
                            </div>
                        @empty
                            <div class="calendar-empty-day">{{ $emptyDayText }}</div>
                        @endforelse
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>
