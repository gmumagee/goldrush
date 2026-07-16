<x-app-layout title="Dashboard">
    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-7xl space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 md:text-3xl">Dashboard</h1>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    {{ $account?->account_name ?? 'Selected account' }} · Account ID {{ session('current_account_id') }}
                </p>
            </div>

            @if (session('status'))
                <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-900/60 dark:bg-green-500/10 dark:text-green-300">{{ session('status') }}</div>
            @endif

            <x-validation-errors />

            <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($metrics as $metric)
                    <div class="panel">
                        <div class="panel-body">
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $metric['label'] }}</p>
                            <p class="mt-2 text-3xl font-semibold text-gray-800 dark:text-gray-100">{{ $metric['value'] }}</p>
                        </div>
                    </div>
                @endforeach
            </section>

            <section class="grid gap-6 xl:grid-cols-2">
                <div class="panel">
                    <div class="panel-header">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Reminders</h2>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Pending dashboard reminders due now or overdue.</p>
                        </div>
                    </div>
                    <div class="panel-body space-y-3">
                        @forelse ($dueReminders as $reminder)
                            <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-700/60">
                                <div class="flex flex-wrap items-start justify-between gap-4">
                                    <div class="space-y-1">
                                        <div class="font-medium text-gray-800 dark:text-gray-100">{{ $reminder->message ?: $reminder->event?->title }}</div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">{{ $reminder->event?->event_type ?? 'Event' }} · Scheduled {{ $reminder->event?->start_at?->format('d-m-Y H:i:s') ?: '—' }}</div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ $reminder->event?->assignedUser?->name ? 'Assigned to '.$reminder->event->assignedUser->name.' · ' : '' }}
                                            {{ collect([$reminder->event?->location?->location_name, $reminder->event?->warehouse?->warehouse_name, $reminder->event?->route?->route_name])->filter()->join(' · ') ?: 'No linked location, warehouse, or route' }}
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        @if ($reminder->event)
                                            <a href="{{ route('calendar-events.show', $reminder->event) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">View Event</a>
                                        @endif
                                        @if ($reminder->event?->sourceRecord() && $reminder->event?->sourceRouteName())
                                            <a href="{{ route($reminder->event->sourceRouteName(), $reminder->event->sourceRecord()) }}" class="inline-flex items-center rounded-xl border border-violet-300 px-3 py-1.5 text-xs font-medium text-violet-700 transition hover:bg-violet-50 dark:border-violet-500/40 dark:text-violet-300 dark:hover:bg-violet-500/10">Open Related Record</a>
                                        @endif
                                        <form method="POST" action="{{ route('calendar-reminders.dismiss', $reminder) }}">
                                            @csrf
                                            <button type="submit" class="inline-flex items-center rounded-xl border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Dismiss</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-xl border border-dashed border-gray-300 px-4 py-6 text-sm text-gray-500 dark:border-gray-700/60 dark:text-gray-400">No reminders are currently due.</div>
                        @endforelse
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Upcoming Events</h2>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Scheduled events for the next 7 days.</p>
                        </div>
                    </div>
                    <div class="panel-body space-y-3">
                        @forelse ($upcomingEvents as $event)
                            <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-700/60">
                                <div class="flex flex-wrap items-start justify-between gap-4">
                                    <div class="space-y-1">
                                        <div class="font-medium text-gray-800 dark:text-gray-100">{{ $event->title }}</div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">{{ $event->event_type }} · {{ $event->start_at?->format('d-m-Y H:i:s') ?: '—' }}</div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ collect([$event->location?->location_name, $event->warehouse?->warehouse_name, $event->route?->route_name])->filter()->join(' · ') ?: 'No linked location, warehouse, or route' }}
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <a href="{{ route('calendar-events.show', $event) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">View Event</a>
                                        @if ($event->sourceRecord() && $event->sourceRouteName())
                                            <a href="{{ route($event->sourceRouteName(), $event->sourceRecord()) }}" class="inline-flex items-center rounded-xl border border-violet-300 px-3 py-1.5 text-xs font-medium text-violet-700 transition hover:bg-violet-50 dark:border-violet-500/40 dark:text-violet-300 dark:hover:bg-violet-500/10">Open Related Record</a>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-xl border border-dashed border-gray-300 px-4 py-6 text-sm text-gray-500 dark:border-gray-700/60 dark:text-gray-400">No scheduled events are coming up in the next 7 days.</div>
                        @endforelse
                    </div>
                </div>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Recent Transactions</h2>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700/60">
                        <thead class="bg-gray-50 dark:bg-gray-800/80">
                            <tr>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Type</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Product</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Machine</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Service</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Quantity</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">When</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700/60">
                            @forelse ($recentTransactions as $transaction)
                                <tr class="bg-white dark:bg-gray-800">
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ ucfirst((string) $transaction->transaction_type) }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $transaction->product?->product_name ?? '—' }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $transaction->machine?->serial_number ?: ($transaction->machine?->type ?? '—') }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $transaction->service_id ? '#'.$transaction->service_id : '—' }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $transaction->quantity }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $transaction->transaction_at?->format('d-m-Y H:i:s') ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr class="bg-white dark:bg-gray-800">
                                    <td colspan="6" class="px-5 py-8 text-center text-gray-500 dark:text-gray-400">No transactions have been recorded yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
