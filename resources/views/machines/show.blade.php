<x-app-layout title="Machine Details">
    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-7xl space-y-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 md:text-3xl">{{ $machine->type }}</h1>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        Machine #{{ $machine->id }} · {{ $machine->serial_number ?: 'No serial' }} · {{ $machine->location?->location_name ?? 'No location' }}
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <a href="{{ route('machines.index') }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                        Back to Machines
                    </a>
                    <a href="{{ route('machines.edit', $machine) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                        Edit Machine
                    </a>
                    <a href="{{ route('machines.bins.edit', $machine) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                        Edit Bins
                    </a>
                    <a href="{{ route('machines.bins.create', $machine) }}" class="inline-flex items-center rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-violet-500">
                        Add Bins
                    </a>
                </div>
            </div>

            <div class="space-y-6">
                <section class="panel">
                    <div class="panel-header">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Machine Summary</h2>
                        </div>
                    </div>
                    <div class="panel-body">
                        <dl class="grid gap-4 text-sm sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
                            <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700/60 dark:bg-gray-900/40">
                                <dt class="text-gray-500 dark:text-gray-400">Location</dt>
                                <dd class="mt-1 truncate font-medium text-gray-800 dark:text-gray-100">{{ $machine->location?->location_name ?? '—' }}</dd>
                            </div>
                            <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700/60 dark:bg-gray-900/40">
                                <dt class="text-gray-500 dark:text-gray-400">Serial</dt>
                                <dd class="mt-1 font-medium text-gray-800 dark:text-gray-100">{{ $machine->serial_number ?: '—' }}</dd>
                            </div>
                            <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700/60 dark:bg-gray-900/40">
                                <dt class="text-gray-500 dark:text-gray-400">Model</dt>
                                <dd class="mt-1 font-medium text-gray-800 dark:text-gray-100">{{ $machine->model ?: '—' }}</dd>
                            </div>
                            <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700/60 dark:bg-gray-900/40">
                                <dt class="text-gray-500 dark:text-gray-400">Status</dt>
                                <dd class="mt-1 font-medium text-gray-800 dark:text-gray-100">{{ $machine->status }}</dd>
                            </div>
                            <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700/60 dark:bg-gray-900/40">
                                <dt class="text-gray-500 dark:text-gray-400">Installed</dt>
                                <dd class="mt-1 font-medium text-gray-800 dark:text-gray-100">{{ \App\Support\AppDateTime::displayDate($machine->installed_on) }}</dd>
                            </div>
                            <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700/60 dark:bg-gray-900/40">
                                <dt class="text-gray-500 dark:text-gray-400">Bin Count</dt>
                                <dd class="mt-1 font-medium text-gray-800 dark:text-gray-100">{{ $machine->bins->count() }}</dd>
                            </div>
                        </dl>
                    </div>
                </section>

                <section class="panel">
                    <div class="panel-header">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Bins</h2>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full table-fixed divide-y divide-gray-200 text-sm dark:divide-gray-700/60">
                            <colgroup>
                                <col class="w-24">
                                <col class="w-auto">
                                <col class="w-28">
                                <col class="w-28">
                                <col class="w-40">
                            </colgroup>
                            <thead class="bg-gray-50 dark:bg-gray-800/80">
                                <tr>
                                    <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Bin</th>
                                    <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Product</th>
                                    <th class="px-5 py-3 text-right font-medium text-gray-500 dark:text-gray-400">Capacity</th>
                                    <th class="px-5 py-3 text-right font-medium text-gray-500 dark:text-gray-400">Price</th>
                                    <th class="px-5 py-3 text-right font-medium text-gray-500 dark:text-gray-400">Current Inventory</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700/60">
                                @forelse ($machine->bins as $bin)
                                    <tr class="bg-white dark:bg-gray-800">
                                        <td class="px-5 py-4 font-medium text-gray-800 dark:text-gray-100">{{ $bin->bin_code }}</td>
                                        <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $bin->product?->product_name ?? '—' }}</td>
                                        <td class="px-5 py-4 text-right tabular-nums text-gray-600 dark:text-gray-300">{{ $bin->capacity }}</td>
                                        <td class="px-5 py-4 text-right tabular-nums text-gray-600 dark:text-gray-300">{{ $bin->price !== null ? number_format((float) $bin->price, 2) : '—' }}</td>
                                        <td class="px-5 py-4 text-right tabular-nums text-gray-600 dark:text-gray-300">{{ $inventoryByBin[$bin->id] ?? 0 }}</td>
                                    </tr>
                                @empty
                                    <tr class="bg-white dark:bg-gray-800">
                                        <td colspan="5" class="px-5 py-8 text-center text-gray-500 dark:text-gray-400">No bins yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
