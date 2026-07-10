<x-app-layout title="Machine Inventory">
    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-7xl space-y-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 md:text-3xl">Machine Inventory</h1>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Manage machines for the selected account.</p>
                </div>

                <a href="{{ route('machines.create') }}" class="inline-flex items-center rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-violet-500">
                    Add Machine
                </a>
            </div>

            @if (session('status'))
                <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-900/60 dark:bg-green-500/10 dark:text-green-300">
                    {{ session('status') }}
                </div>
            @endif

            <section class="panel">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700/60">
                        <thead class="bg-gray-50 dark:bg-gray-800/80">
                            <tr>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Machine</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Location</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Serial</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Model</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Status</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Installed</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Bins</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700/60">
                            @forelse ($machines as $machine)
                                <tr class="bg-white dark:bg-gray-800">
                                    <td class="px-5 py-4">
                                        <a href="{{ route('machines.show', $machine) }}" class="block rounded-lg text-gray-800 transition hover:text-violet-600 dark:text-gray-100 dark:hover:text-violet-300">
                                            <div class="font-semibold">{{ $machine->type }}</div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">Machine #{{ $machine->id }}</div>
                                        </a>
                                    </td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $machine->location?->location_name ?? '—' }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $machine->serial_number ?: '—' }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $machine->model ?: '—' }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $machine->status }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $machine->installed_on?->format('Y-m-d') ?? '—' }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $machine->bins_count }}</td>
                                    <td class="px-5 py-4">
                                        <div class="flex items-center gap-2">
                                            <a href="{{ route('machines.show', $machine) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                                                View Bins
                                            </a>
                                            <a href="{{ route('machines.bins.create', $machine) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                                                Add Bins
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr class="bg-white dark:bg-gray-800">
                                    <td colspan="8" class="px-5 py-8 text-center text-gray-500 dark:text-gray-400">
                                        No machines found for this account.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
