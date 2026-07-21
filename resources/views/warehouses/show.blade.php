<x-app-layout title="Warehouse Details">
    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-7xl space-y-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 md:text-3xl">{{ $warehouse->warehouse_name }}</h1>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Warehouse #{{ $warehouse->id }}</p>
                </div>
                <div class="flex gap-3">
                    <a href="{{ route('warehouses.index') }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Back to Warehouses</a>
                    @can('create', \App\Models\CalendarEvent::class)
                        <a href="{{ route('calendar-events.create', ['source_type' => 'warehouse', 'source_id' => $warehouse->id]) }}" class="inline-flex items-center rounded-xl border border-violet-300 px-4 py-2 text-sm font-medium text-violet-700 transition hover:bg-violet-50 dark:border-violet-500/40 dark:text-violet-300 dark:hover:bg-violet-500/10">Schedule Event</a>
                    @endcan
                    @can('update', $warehouse)
                        <a href="{{ route('warehouses.edit', $warehouse) }}" class="inline-flex items-center rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-violet-500">Edit Warehouse</a>
                    @endcan
                    @can('create', \App\Models\Purchase::class)
                        <a href="{{ route('purchases.create', ['warehouse_id' => $warehouse->id]) }}" class="inline-flex items-center rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-blue-500">Create Purchase</a>
                    @endcan
                </div>
            </div>
            @if (session('status'))
                <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-900/60 dark:bg-green-500/10 dark:text-green-300">{{ session('status') }}</div>
            @endif
            <x-validation-errors />
            <section class="panel">
                <div class="panel-body">
                    <dl class="grid gap-4 text-sm md:grid-cols-2">
                        <div><dt class="text-gray-500 dark:text-gray-400">Address</dt><dd class="mt-1 text-gray-800 dark:text-gray-100">{{ $warehouse->address ?: '—' }}</dd></div>
                        <div><dt class="text-gray-500 dark:text-gray-400">City</dt><dd class="mt-1 text-gray-800 dark:text-gray-100">{{ $warehouse->city ?: '—' }}</dd></div>
                        <div><dt class="text-gray-500 dark:text-gray-400">State</dt><dd class="mt-1 text-gray-800 dark:text-gray-100">{{ $warehouse->state ?: '—' }}</dd></div>
                        <div><dt class="text-gray-500 dark:text-gray-400">Zip Code</dt><dd class="mt-1 text-gray-800 dark:text-gray-100">{{ $warehouse->zip_code ?: '—' }}</dd></div>
                    </dl>
                </div>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Warehouse Inventory</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Quantity on hand, average unit cost, and inventory value derived from the inventory ledger.</p>
                    </div>
                </div>
                <div class="panel-body border-b border-gray-200 dark:border-gray-700/60">
                    <form method="GET" action="{{ route('warehouses.show', $warehouse) }}" class="grid gap-4 md:grid-cols-[1fr_auto]">
                        <x-input name="search" type="text" :value="$search" placeholder="Search SKU, product, brand, or category" />
                        <div class="flex gap-3">
                            <x-button>Search</x-button>
                            <a href="{{ route('warehouses.show', $warehouse) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Reset</a>
                        </div>
                    </form>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700/60">
                        <thead class="bg-gray-50 dark:bg-gray-800/80">
                            <tr>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">SKU</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Product</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Size</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Package Type</th>
                                <th class="px-5 py-3 text-right font-medium text-gray-500 dark:text-gray-400">Quantity On Hand</th>
                                <th class="px-5 py-3 text-right font-medium text-gray-500 dark:text-gray-400">Average Unit Cost</th>
                                <th class="px-5 py-3 text-right font-medium text-gray-500 dark:text-gray-400">Inventory Value</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700/60">
                            @forelse ($inventoryRows as $row)
                                <tr class="bg-white dark:bg-gray-800">
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $row->sku ?: '—' }}</td>
                                    <td class="px-5 py-4 font-medium text-gray-800 dark:text-gray-100">{{ $row->product_name }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $row->size ?: '—' }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $row->package_type ?: '—' }}</td>
                                    <td class="px-5 py-4 text-right tabular-nums text-gray-600 dark:text-gray-300">{{ (int) $row->quantity_on_hand }}</td>
                                    <td class="px-5 py-4 text-right tabular-nums text-gray-600 dark:text-gray-300">{{ number_format((float) $row->average_unit_cost, 4) }}</td>
                                    <td class="px-5 py-4 text-right tabular-nums text-gray-600 dark:text-gray-300">{{ number_format((float) $row->inventory_value, 4) }}</td>
                                </tr>
                            @empty
                                <tr class="bg-white dark:bg-gray-800">
                                    <td colspan="7" class="px-5 py-8 text-center text-gray-500 dark:text-gray-400">No warehouse inventory has been posted yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Recent Inventory Ledger</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Recent append-only inventory movements for this warehouse.</p>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700/60">
                        <thead class="bg-gray-50 dark:bg-gray-800/80">
                            <tr>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Date</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Time</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Product</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Movement Type</th>
                                <th class="px-5 py-3 text-right font-medium text-gray-500 dark:text-gray-400">Quantity Delta</th>
                                <th class="px-5 py-3 text-right font-medium text-gray-500 dark:text-gray-400">Unit Cost</th>
                                <th class="px-5 py-3 text-right font-medium text-gray-500 dark:text-gray-400">Total Cost</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700/60">
                            @forelse ($recentLedger as $entry)
                                <tr class="bg-white dark:bg-gray-800">
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ \App\Support\AppDateTime::displayDate($entry->movement_at) }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ \App\Support\AppDateTime::displayTime($entry->movement_at) }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $entry->product?->product_name ?? '—' }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $movementTypeLabels[strtolower(trim((string) $entry->movement_type))] ?? \Illuminate\Support\Str::headline(str_replace('_', ' ', $entry->movement_type)) }}</td>
                                    <td class="px-5 py-4 text-right tabular-nums text-gray-600 dark:text-gray-300">{{ $entry->quantity_delta }}</td>
                                    <td class="px-5 py-4 text-right tabular-nums text-gray-600 dark:text-gray-300">{{ number_format((float) $entry->unit_cost, 4) }}</td>
                                    <td class="px-5 py-4 text-right tabular-nums text-gray-600 dark:text-gray-300">{{ number_format((float) $entry->total_cost, 4) }}</td>
                                </tr>
                            @empty
                                <tr class="bg-white dark:bg-gray-800">
                                    <td colspan="7" class="px-5 py-8 text-center text-gray-500 dark:text-gray-400">No inventory ledger entries exist for this warehouse.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
