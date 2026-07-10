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
                                <dd class="mt-1 font-medium text-gray-800 dark:text-gray-100">{{ $machine->installed_on?->format('Y-m-d') ?? '—' }}</dd>
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
                    <div class="p-5">
                        @php
                            $rows = $machine->bins
                                ->sortBy('bin_code')
                                ->groupBy(function ($bin) {
                                    if (preg_match('/^([A-Z]+)/', strtoupper($bin->bin_code), $matches)) {
                                        return $matches[1];
                                    }

                                    return 'OTHER';
                                });
                        @endphp
                        <div class="space-y-3">
                            @forelse ($rows as $row => $rowBins)
                                <details class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700/60 dark:bg-gray-800">
                                    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 font-medium text-gray-800 marker:hidden dark:text-gray-100">
                                        <span>Row: {{ $row === 'OTHER' ? 'Other' : $row }}</span>
                                        <span aria-hidden="true" class="text-lg leading-none text-gray-400 dark:text-gray-500">+</span>
                                    </summary>
                                    <div
                                        class="grid gap-3 border-t border-gray-200 bg-gray-50 px-4 py-4 dark:border-gray-700/60 dark:bg-gray-900/40"
                                        style="grid-template-columns: repeat({{ max($rowBins->count(), 1) }}, minmax(0, 1fr));"
                                    >
                                        @foreach ($rowBins as $bin)
                                            @php
                                                $currentInventory = (int) ($bin->current_inventory ?? 0);
                                            @endphp
                                            <div class="min-w-0 rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm dark:border-gray-700/60 dark:bg-gray-800">
                                                <div class="font-medium text-gray-800 dark:text-gray-100">{{ $bin->bin_code }}</div>
                                                <div class="mt-3 space-y-3">
                                                    <div>
                                                        <div class="text-gray-500 dark:text-gray-400">Product</div>
                                                        <div class="mt-1 text-gray-800 dark:text-gray-100">{{ $bin->product?->product_name ?? '—' }}</div>
                                                    </div>
                                                    <div>
                                                        <div class="text-gray-500 dark:text-gray-400">Capacity</div>
                                                        <div class="mt-1 text-gray-800 dark:text-gray-100">{{ $bin->capacity }}</div>
                                                    </div>
                                                    <div>
                                                        <div class="text-gray-500 dark:text-gray-400">Current Inventory</div>
                                                        <div class="mt-1 text-gray-800 dark:text-gray-100">{{ $currentInventory }}</div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </details>
                            @empty
                                <div class="rounded-xl border border-dashed border-gray-300 px-4 py-6 text-sm text-gray-500 dark:border-gray-700/60 dark:text-gray-400">
                                    No bins yet.
                                </div>
                            @endforelse
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
