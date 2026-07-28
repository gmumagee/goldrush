<x-app-layout title="Import / Export">
    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-4xl space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 md:text-3xl">Import / Export</h1>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Export account-scoped CSV templates, edit them, and re-import them safely through a preview-first workflow.</p>
            </div>

            @if (session('status'))
                <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-900/60 dark:bg-green-500/10 dark:text-green-300">{{ session('status') }}</div>
            @endif

            <x-validation-errors />

            <section
                class="panel"
                x-data="{
                    activeTab: '{{ $activeTab }}',
                    exportEntity: '{{ $defaultExportEntity }}',
                    importEntity: '{{ $selectedImportEntity ?? $defaultImportEntity }}',
                    exportBaseUrl: '{{ url('/import-export/export') }}',
                }"
            >
                <div class="panel-body border-b border-gray-200 dark:border-gray-700/60">
                    <div class="flex flex-wrap gap-3">
                        <button
                            type="button"
                            class="inline-flex items-center rounded-xl px-4 py-2 text-sm font-medium transition"
                            :class="activeTab === 'export'
                                ? 'bg-violet-500/10 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300'
                                : 'border border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700'"
                            @click="activeTab = 'export'"
                        >
                            Export
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center rounded-xl px-4 py-2 text-sm font-medium transition"
                            :class="activeTab === 'import'
                                ? 'bg-violet-500/10 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300'
                                : 'border border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700'"
                            @click="activeTab = 'import'"
                        >
                            Import
                        </button>
                    </div>
                </div>

                <div class="panel-body space-y-6" x-show="activeTab === 'export'">
                    <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600 dark:border-gray-700/60 dark:bg-gray-900/30 dark:text-gray-300">
                        Export files are designed to become the future import template. Human-readable reference columns are included so edited CSVs can round-trip cleanly in the next phase.
                    </div>

                    <form method="GET" :action="`${exportBaseUrl}/${exportEntity}`" class="grid gap-4 md:grid-cols-[minmax(0,320px)_auto] md:items-end">
                        <div class="space-y-2">
                            <label for="export-entity" class="text-sm font-medium text-gray-700 dark:text-gray-200">Entity</label>
                            <select
                                id="export-entity"
                                x-model="exportEntity"
                                class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                            >
                                @foreach ($availableExportEntities as $availableEntity)
                                    <option value="{{ $availableEntity['key'] }}">{{ $availableEntity['label'] }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <x-button type="submit">Export CSV</x-button>
                        </div>
                    </form>
                </div>

                <div class="panel-body" x-cloak x-show="activeTab === 'import'">
                    <div class="space-y-6">
                        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
                            Import order matters: import locations first, then machines and contacts that reference those locations. Reference resolution is strict, so missing or ambiguous names will preview as errors and will not be committed.
                        </div>

                        <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600 dark:border-gray-700/60 dark:bg-gray-900/30 dark:text-gray-300">
                            The import file format matches export. Export a CSV from the other tab, edit it, then re-import it here.
                        </div>

                        @if ($availableImportEntities === [])
                            <div class="rounded-2xl border border-dashed border-gray-300 bg-gray-50 px-6 py-8 text-center dark:border-gray-700 dark:bg-gray-900/30">
                                <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Import unavailable</h2>
                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Your role can export data, but it does not have permission to create or update records through CSV import.</p>
                            </div>
                        @else
                            <form method="POST" action="{{ route('import-export.import.analyze') }}" enctype="multipart/form-data" class="grid gap-4 md:grid-cols-[minmax(0,220px)_minmax(0,1fr)_auto] md:items-end">
                                @csrf
                                <div class="space-y-2">
                                    <label for="import-entity" class="text-sm font-medium text-gray-700 dark:text-gray-200">Entity</label>
                                    <select
                                        id="import-entity"
                                        name="entity"
                                        x-model="importEntity"
                                        class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                                    >
                                        @foreach ($availableImportEntities as $availableEntity)
                                            <option value="{{ $availableEntity['key'] }}">{{ $availableEntity['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="space-y-2">
                                    <label for="import-file" class="text-sm font-medium text-gray-700 dark:text-gray-200">CSV File</label>
                                    <input
                                        id="import-file"
                                        name="import_file"
                                        type="file"
                                        accept=".csv,text/csv"
                                        class="block w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm file:mr-4 file:rounded-lg file:border-0 file:bg-violet-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-violet-700 hover:file:bg-violet-100 focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 dark:file:bg-violet-500/10 dark:file:text-violet-300"
                                    >
                                </div>

                                <div>
                                    <x-button type="submit">Analyze</x-button>
                                </div>
                            </form>

                            @if ($importPreview)
                                <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700/60 dark:bg-gray-900/30">
                                    <div class="flex flex-wrap items-center justify-between gap-4">
                                        <div>
                                            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Preview</h2>
                                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Nothing has been written yet. Review the analyzed rows below, then confirm to commit the valid rows in one transaction.</p>
                                        </div>
                                        <div class="flex flex-wrap gap-2 text-xs font-medium">
                                            <span class="inline-flex rounded-full bg-green-100 px-3 py-1 text-green-800 dark:bg-green-500/15 dark:text-green-300">{{ $importPreview['counts']['create'] }} create</span>
                                            <span class="inline-flex rounded-full bg-blue-100 px-3 py-1 text-blue-800 dark:bg-blue-500/15 dark:text-blue-300">{{ $importPreview['counts']['update'] }} update</span>
                                            <span class="inline-flex rounded-full bg-red-100 px-3 py-1 text-red-800 dark:bg-red-500/15 dark:text-red-300">{{ $importPreview['counts']['error'] }} error</span>
                                            <span class="inline-flex rounded-full bg-amber-100 px-3 py-1 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300">{{ $importPreview['counts']['duplicate_warning'] }} warning</span>
                                        </div>
                                    </div>

                                    <div class="mt-5 overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700/60">
                                            <thead class="bg-gray-50 dark:bg-gray-800/80">
                                                <tr>
                                                    <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Row</th>
                                                    <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Key</th>
                                                    <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Action</th>
                                                    <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Message</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700/60">
                                                @foreach ($importPreview['rows'] as $row)
                                                    <tr class="bg-white dark:bg-gray-800">
                                                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $row['row_number'] }}</td>
                                                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $row['key'] ?: '—' }}</td>
                                                        <td class="px-4 py-3">
                                                            @if ($row['action'] === 'create')
                                                                <span class="inline-flex rounded-full bg-green-100 px-2.5 py-1 text-xs font-medium text-green-800 dark:bg-green-500/15 dark:text-green-300">Create</span>
                                                            @elseif ($row['action'] === 'update')
                                                                <span class="inline-flex rounded-full bg-blue-100 px-2.5 py-1 text-xs font-medium text-blue-800 dark:bg-blue-500/15 dark:text-blue-300">Update</span>
                                                            @else
                                                                <span class="inline-flex rounded-full bg-red-100 px-2.5 py-1 text-xs font-medium text-red-800 dark:bg-red-500/15 dark:text-red-300">Error</span>
                                                            @endif
                                                        </td>
                                                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $row['message'] ?: 'Ready to import.' }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="mt-5 flex flex-wrap gap-3">
                                        <form method="POST" action="{{ route('import-export.import.confirm') }}">
                                            @csrf
                                            <input type="hidden" name="entity" value="{{ $importPreview['entity'] }}">
                                            <input type="hidden" name="token" value="{{ $importPreview['token'] }}">
                                            <x-button
                                                type="submit"
                                                :disabled="($importPreview['counts']['create'] + $importPreview['counts']['update']) === 0"
                                            >
                                                Confirm Import
                                            </x-button>
                                        </form>
                                        <a href="{{ route('import-export.index') }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Cancel</a>
                                    </div>
                                </div>
                            @endif
                        @endif
                    </div>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
