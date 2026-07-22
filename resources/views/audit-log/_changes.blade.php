@props(['entry'])

<div class="space-y-2">
    <div class="text-sm text-gray-700 dark:text-gray-200">{{ $entry->changeSummaryLabel() }}</div>

    @if ($entry->changeLines() !== [])
        <div class="space-y-1 text-xs text-gray-500 dark:text-gray-400">
            @foreach ($entry->previewChangeLines() as $line)
                <div>{{ $line }}</div>
            @endforeach
        </div>

        @if ($entry->hasHiddenChangeLines() || ! in_array($entry->event, [\App\Models\AuditLog::EVENT_UPDATED], true))
            <details class="group">
                <summary class="cursor-pointer text-xs font-medium text-violet-700 transition hover:text-violet-600 dark:text-violet-300 dark:hover:text-violet-200">
                    View details
                </summary>

                <div class="mt-2 space-y-1 rounded-xl border border-gray-200 bg-gray-50 px-3 py-3 text-xs text-gray-600 dark:border-gray-700/60 dark:bg-gray-900/40 dark:text-gray-300">
                    @foreach ($entry->changeLines() as $line)
                        <div>{{ $line }}</div>
                    @endforeach
                </div>
            </details>
        @endif
    @endif
</div>
