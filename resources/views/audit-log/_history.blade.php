@props([
    'auditable',
    'auditEntries' => null,
    'showAccount' => false,
])

@php
    $historyEntries = $auditEntries
        ?? $auditable->auditLogs()->with(['user', 'account'])->orderByDesc('created_at')->orderByDesc('id')->get();
@endphp

<section class="panel">
    <div class="panel-header">
        <div>
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">History</h2>
        </div>
    </div>

    @if ($historyEntries->isEmpty())
        <div class="panel-body">
            <div class="rounded-xl border border-dashed border-gray-200 bg-gray-50 px-5 py-8 text-center text-sm text-gray-500 dark:border-gray-700/60 dark:bg-gray-900/30 dark:text-gray-400">
                No audit entries for this record yet.
            </div>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700/60">
                <thead class="bg-gray-50 dark:bg-gray-800/80">
                    <tr>
                        <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Timestamp</th>
                        @if ($showAccount)
                            <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Account</th>
                        @endif
                        <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">User</th>
                        <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Event</th>
                        <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Changes</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700/60">
                    @foreach ($historyEntries as $entry)
                        <tr class="bg-white align-top dark:bg-gray-800">
                            <td class="px-5 py-4 text-gray-600 dark:text-gray-300">
                                <div>{{ \App\Support\AppDateTime::displayDate($entry->created_at) }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ \App\Support\AppDateTime::displayTime($entry->created_at) }}</div>
                            </td>
                            @if ($showAccount)
                                <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $entry->account?->account_name ?? '—' }}</td>
                            @endif
                            <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $entry->userDisplayName() }}</td>
                            <td class="px-5 py-4">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $entry->eventBadgeClasses() }}">{{ ucfirst($entry->event) }}</span>
                            </td>
                            <td class="px-5 py-4">
                                @include('audit-log._changes', ['entry' => $entry])
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
