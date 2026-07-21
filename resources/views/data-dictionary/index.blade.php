<x-app-layout title="Data Dictionary">
    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-7xl space-y-6">
            @php
                $scopeBadge = static function (bool $isGlobal): string {
                    return $isGlobal
                        ? 'bg-slate-100 text-slate-700 dark:bg-slate-700/60 dark:text-slate-200'
                        : 'bg-blue-100 text-blue-800 dark:bg-blue-500/15 dark:text-blue-300';
                };
                $statusBadge = static function (bool $isActive): string {
                    return $isActive
                        ? 'bg-green-100 text-green-800 dark:bg-green-500/15 dark:text-green-300'
                        : 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300';
                };
            @endphp

            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 md:text-3xl">Data Dictionary</h1>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">View global dictionary defaults and manage account-specific values for the current account.</p>
                </div>
                @can('create', \App\Models\DataDictionary::class)
                    <a href="{{ route('data-dictionary.create', ['name' => $filters['name']]) }}" class="inline-flex items-center rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-violet-500">Add Dictionary Value</a>
                @endcan
            </div>

            @if (session('status'))
                <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-900/60 dark:bg-green-500/10 dark:text-green-300">{{ session('status') }}</div>
            @endif

            <x-validation-errors />

            <section class="panel">
                <div class="panel-body space-y-4 border-b border-gray-200 dark:border-gray-700/60">
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-200">
                        Global dictionary values are read-only here. Owner and Admin users can create, edit, activate, and deactivate account-specific values only.
                    </div>

                    <form method="GET" action="{{ route('data-dictionary.index') }}" class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_180px_minmax(0,1fr)_auto]">
                        <div>
                            <label for="name" class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">Name</label>
                            <select id="name" name="name" class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100">
                                <option value="">All names</option>
                                @foreach ($names as $name)
                                    <option value="{{ $name }}" @selected($filters['name'] === $name)>{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="active_status" class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">Status</label>
                            <select id="active_status" name="active_status" class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100">
                                <option value="active" @selected($filters['active_status'] === 'active')>Active</option>
                                <option value="inactive" @selected($filters['active_status'] === 'inactive')>Inactive</option>
                                <option value="all" @selected($filters['active_status'] === 'all')>All</option>
                            </select>
                        </div>

                        <div>
                            <label for="search" class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">Search</label>
                            <x-input id="search" name="search" type="text" :value="$filters['search']" placeholder="Name, value, or display name" />
                        </div>

                        <div class="flex items-end gap-3">
                            <x-button>Filter</x-button>
                            <a href="{{ route('data-dictionary.index') }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Reset</a>
                        </div>
                    </form>

                    @if ($names->isNotEmpty())
                        <div class="flex flex-wrap gap-2">
                            @foreach ($names as $name)
                                <a
                                    href="{{ route('data-dictionary.index', ['name' => $name, 'active_status' => $filters['active_status'], 'search' => $filters['search']]) }}"
                                    class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium transition {{ $filters['name'] === $name ? 'bg-violet-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700/60 dark:text-gray-200 dark:hover:bg-gray-700' }}"
                                >
                                    {{ $name }}
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="overflow-x-auto p-5">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700/60">
                        <thead class="bg-white dark:bg-gray-800">
                            <tr>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Name</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Value</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Display Name</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Sort Order</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Scope</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Status</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700/60">
                            @forelse ($entries as $entry)
                                <tr class="bg-white dark:bg-gray-800">
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span>{{ $entry->name }}</span>
                                            @if ($entry->isProtectedGlobal())
                                                <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-800 dark:bg-amber-500/15 dark:text-amber-300">Protected</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $entry->value }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $entry->displayLabel() }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $entry->sort_order }}</td>
                                    <td class="px-5 py-4">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $scopeBadge($entry->isGlobal()) }}">{{ $entry->isGlobal() ? 'Global' : 'Account' }}</span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $statusBadge($entry->is_active) }}">{{ $entry->is_active ? 'Active' : 'Inactive' }}</span>
                                    </td>
                                    <td class="px-5 py-4">
                                        @if ($entry->isGlobal())
                                            <span class="text-xs font-medium text-gray-400 dark:text-gray-500">View only</span>
                                        @else
                                            @can('update', $entry)
                                                <div class="flex flex-wrap gap-2">
                                                    <a href="{{ route('data-dictionary.edit', $entry) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Edit</a>
                                                    @if ($entry->is_active)
                                                        <form method="POST" action="{{ route('data-dictionary.deactivate', $entry) }}">
                                                            @csrf
                                                            <button type="submit" class="inline-flex items-center rounded-xl border border-amber-300 px-3 py-1.5 text-xs font-medium text-amber-700 transition hover:bg-amber-50 dark:border-amber-500/40 dark:text-amber-300 dark:hover:bg-amber-500/10">Deactivate</button>
                                                        </form>
                                                    @else
                                                        <form method="POST" action="{{ route('data-dictionary.activate', $entry) }}">
                                                            @csrf
                                                            <button type="submit" class="inline-flex items-center rounded-xl border border-green-300 px-3 py-1.5 text-xs font-medium text-green-700 transition hover:bg-green-50 dark:border-green-500/40 dark:text-green-300 dark:hover:bg-green-500/10">Reactivate</button>
                                                        </form>
                                                    @endif
                                                </div>
                                            @else
                                                <span class="text-xs font-medium text-gray-400 dark:text-gray-500">View only</span>
                                            @endcan
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr class="bg-white dark:bg-gray-800">
                                    <td colspan="7" class="px-5 py-8 text-center text-gray-500 dark:text-gray-400">No dictionary values matched the current filters.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
