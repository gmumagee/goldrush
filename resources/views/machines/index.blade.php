<x-app-layout title="Machines">
    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-7xl space-y-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 md:text-3xl">Machines</h1>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Manage machines for the selected account.</p>
                </div>
                <a href="{{ route('machines.create') }}" class="inline-flex items-center rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-violet-500">Add Machine</a>
            </div>
            @if (session('status'))
                <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-900/60 dark:bg-green-500/10 dark:text-green-300">{{ session('status') }}</div>
            @endif
            <x-validation-errors />
            <section class="panel">
                <div class="panel-body border-b border-gray-200 dark:border-gray-700/60">
                    <form method="GET" action="{{ route('machines.index') }}" class="grid gap-4 md:grid-cols-[1fr_auto]">
                        <x-input name="search" type="text" :value="$search" placeholder="Search serial number, model, or status" />
                        <div class="flex gap-3">
                            <x-button>Search</x-button>
                            <a href="{{ route('machines.index') }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Reset</a>
                        </div>
                    </form>
                </div>
                @if ($machineGroups->isEmpty())
                    <div class="px-5 py-8 text-center text-gray-500 dark:text-gray-400">No machines found for this account.</div>
                @else
                    <div class="space-y-4 p-4">
                        @foreach ($machineGroups as $group)
                            @php
                                // Use stable loop-based IDs so every machine-type accordion keeps unique control relationships on the page.
                                $groupBaseId = 'machine-type-group-'.$loop->index;
                                $groupHeadingId = $groupBaseId.'-heading';
                                $groupCollapseId = $groupBaseId.'-collapse';
                            @endphp
                            <div x-data="{ open: false }" class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700/60">
                                <button
                                    type="button"
                                    class="flex w-full items-center justify-between gap-4 bg-gray-50 px-4 py-3 text-left dark:bg-gray-800/80"
                                    @click="open = !open"
                                    :aria-expanded="open.toString()"
                                    aria-controls="{{ $groupCollapseId }}"
                                    id="{{ $groupHeadingId }}"
                                >
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="text-lg font-semibold text-gray-800 dark:text-gray-100">{{ $group['label'] }}</span>
                                            <span class="inline-flex rounded-full bg-gray-200 px-2.5 py-1 text-xs font-medium text-gray-700 dark:bg-gray-700/60 dark:text-gray-200">
                                                {{ $group['count'] }}
                                                <span class="sr-only">machines</span>
                                            </span>
                                        </div>
                                    </div>
                                    <span class="inline-flex h-4 w-4 shrink-0 items-center justify-center text-sm leading-none text-gray-400 transition-transform duration-200" :class="open ? 'rotate-90' : ''" aria-hidden="true">›</span>
                                </button>

                                <div
                                    id="{{ $groupCollapseId }}"
                                    x-show="open"
                                    x-transition.origin.top.duration.200ms
                                    class="border-t border-gray-200 bg-white dark:border-gray-700/60 dark:bg-gray-900/30"
                                    aria-labelledby="{{ $groupHeadingId }}"
                                >
                                    <div class="table-responsive overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700/60">
                                            <thead class="bg-gray-50 dark:bg-gray-800/80">
                                                <tr>
                                                    <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Serial Number</th>
                                                    <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Model</th>
                                                    <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Location</th>
                                                    <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Status</th>
                                                    <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Installed On</th>
                                                    <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700/60">
                                                @foreach ($group['machines'] as $machine)
                                                    @php
                                                        // Normalize stored statuses at render time so casing or whitespace differences still map to the required pill styles without changing database values.
                                                        $machineStatus = strtolower(trim((string) $machine->status));
                                                    @endphp
                                                    <tr class="bg-white dark:bg-gray-800">
                                                        <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $machine->serial_number ?: '—' }}</td>
                                                        <td class="px-5 py-4 font-medium text-gray-800 dark:text-gray-100">{{ $machine->model ?: '—' }}</td>
                                                        <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $machine->location?->location_name ?? '—' }}</td>
                                                        <td class="px-5 py-4 text-gray-600 dark:text-gray-300">
                                                            @if ($machineStatus === 'active')
                                                                <span class="inline-flex rounded-full bg-blue-100 px-2.5 py-1 text-xs font-medium text-blue-800 dark:bg-blue-500/15 dark:text-blue-300">Active</span>
                                                            @elseif ($machineStatus === 'inactive')
                                                                <span class="inline-flex rounded-full bg-green-100 px-2.5 py-1 text-xs font-medium text-green-800 dark:bg-green-500/15 dark:text-green-300">Inactive</span>
                                                            @else
                                                                <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700 dark:bg-gray-700/60 dark:text-gray-200">{{ trim((string) $machine->status) !== '' ? trim((string) $machine->status) : 'Unknown' }}</span>
                                                            @endif
                                                        </td>
                                                        <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ \App\Support\AppDateTime::displayDate($machine->installed_on) ?: '—' }}</td>
                                                        <td class="px-5 py-4">
                                                            <div class="flex flex-wrap gap-2">
                                                                <a href="{{ route('machines.show', $machine) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">View</a>
                                                                <a href="{{ route('machines.edit', $machine) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Edit</a>
                                                                <a href="{{ route('bins.create', ['machine_id' => $machine->id]) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Add Bin</a>
                                                                <form method="POST" action="{{ route('machines.destroy', $machine) }}">
                                                                    @csrf
                                                                    @method('DELETE')
                                                                    <button type="submit" class="inline-flex items-center rounded-xl border border-red-300 px-3 py-1.5 text-xs font-medium text-red-700 transition hover:bg-red-50 dark:border-red-500/40 dark:text-red-300 dark:hover:bg-red-500/10">Delete</button>
                                                                </form>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
                <div class="panel-body">{{ $machines->links() }}</div>
            </section>
        </div>
    </div>
</x-app-layout>
