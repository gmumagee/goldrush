<x-app-layout title="Audit Log">
    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-7xl space-y-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 md:text-3xl">Audit Log</h1>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Review accountability history for financial and inventory records.</p>
                </div>
            </div>

            @if (session('status'))
                <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-900/60 dark:bg-green-500/10 dark:text-green-300">{{ session('status') }}</div>
            @endif

            <x-validation-errors />

            <section class="panel">
                <div class="panel-body border-b border-gray-200 dark:border-gray-700/60">
                    <form method="GET" action="{{ route('audit-log.index') }}" class="grid gap-4 md:grid-cols-[220px_260px_auto]">
                        <select name="event" class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100">
                            <option value="">All events</option>
                            @foreach ($eventOptions as $eventOption)
                                <option value="{{ $eventOption }}" @selected($filters['event'] === $eventOption)>{{ ucfirst($eventOption) }}</option>
                            @endforeach
                        </select>

                        <select name="entity_type" class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100">
                            <option value="">All entities</option>
                            @foreach ($entityTypeOptions as $entityClass => $entityLabel)
                                <option value="{{ $entityClass }}" @selected($filters['entity_type'] === $entityClass)>{{ $entityLabel }}</option>
                            @endforeach
                        </select>

                        <div class="flex gap-3">
                            <x-button>Filter</x-button>
                            <a href="{{ route('audit-log.index') }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Reset</a>
                        </div>
                    </form>
                </div>

                @if ($auditEntries->isEmpty())
                    <div class="panel-body">
                        <div class="rounded-xl border border-dashed border-gray-200 bg-gray-50 px-5 py-8 text-center text-sm text-gray-500 dark:border-gray-700/60 dark:bg-gray-900/30 dark:text-gray-400">
                            No audit entries found.
                        </div>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700/60">
                            <thead class="bg-gray-50 dark:bg-gray-800/80">
                                <tr>
                                    <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Timestamp</th>
                                    @if ($showAllAccounts)
                                        <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Account</th>
                                    @endif
                                    <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">User</th>
                                    <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Event</th>
                                    <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Entity Type</th>
                                    <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Entity ID</th>
                                    <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Changes</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700/60">
                                @foreach ($auditEntries as $entry)
                                    <tr class="bg-white align-top dark:bg-gray-800">
                                        <td class="px-5 py-4 text-gray-600 dark:text-gray-300">
                                            <div>{{ \App\Support\AppDateTime::displayDate($entry->created_at) }}</div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ \App\Support\AppDateTime::displayTime($entry->created_at) }}</div>
                                        </td>
                                        @if ($showAllAccounts)
                                            <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $entry->account?->account_name ?? '—' }}</td>
                                        @endif
                                        <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $entry->userDisplayName() }}</td>
                                        <td class="px-5 py-4">
                                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $entry->eventBadgeClasses() }}">{{ ucfirst($entry->event) }}</span>
                                        </td>
                                        <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $entry->entityLabel() }}</td>
                                        <td class="px-5 py-4 text-gray-600 dark:text-gray-300">#{{ $entry->auditable_id }}</td>
                                        <td class="px-5 py-4">
                                            @include('audit-log._changes', ['entry' => $entry])
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="panel-body">{{ $auditEntries->links() }}</div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
