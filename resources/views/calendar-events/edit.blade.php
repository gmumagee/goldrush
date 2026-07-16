<x-app-layout title="Edit Calendar Event">
    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-4xl space-y-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 md:text-3xl">Edit Calendar Event</h1>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Update the scheduled details and dashboard reminder for this event.</p>
                </div>
                <a href="{{ route('calendar-events.show', $calendarEvent) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Back to Event</a>
            </div>

            <section class="panel">
                <div class="panel-body space-y-5">
                    <form method="POST" action="{{ route('calendar-events.update', $calendarEvent) }}" class="space-y-5">
                        @csrf
                        @method('PATCH')
                        @include('calendar-events._form')
                    </form>

                    <x-validation-errors />
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
