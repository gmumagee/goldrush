<x-app-layout title="Dashboard">
    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-7xl space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 md:text-3xl">Dashboard</h1>
            </div>

            @include('calendar-events._week', [
                'title' => 'Weekly Calendar',
                'weekStart' => $weekStart,
                'weekEnd' => $weekEnd,
                'weekDays' => $weekDays,
                'eventsByDate' => $eventsByDate,
                'previousWeekUrl' => route('dashboard', ['date' => $weekStart->copy()->subWeek()->toDateString()]),
                'currentWeekUrl' => route('dashboard', ['date' => now()->toDateString()]),
                'nextWeekUrl' => route('dashboard', ['date' => $weekStart->copy()->addWeek()->toDateString()]),
                'emptyDayText' => 'No scheduled events.',
            ])

            @if (session('status'))
                <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-900/60 dark:bg-green-500/10 dark:text-green-300">{{ session('status') }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
