<x-app-layout title="Inventory On Hand">
    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-6xl space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 md:text-3xl">Inventory On Hand</h1>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Current inventory snapshot by warehouse, category, and product, derived from the live inventory ledger.</p>
            </div>

            <section class="panel">
                <div class="panel-body space-y-6">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Current Snapshot</h2>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">This report reflects current on-hand balances only. It does not use a date filter.</p>
                        </div>
                        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-right dark:border-emerald-500/30 dark:bg-emerald-500/10">
                            <div class="text-xs font-medium uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Grand Total</div>
                            <div class="mt-1 text-2xl font-semibold text-emerald-800 dark:text-emerald-200">{{ number_format($grandTotalQuantity) }} units</div>
                            <div class="mt-1 text-sm text-emerald-700 dark:text-emerald-300">{{ $grandTotalValueDisplay }} value</div>
                        </div>
                    </div>

                    @unless ($hasData)
                        <div class="rounded-2xl border border-dashed border-gray-300 bg-gray-50 px-6 py-4 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900/30 dark:text-gray-400">
                            No inventory ledger balances are available yet.
                        </div>
                    @endunless

                    <div class="space-y-6">
                        @foreach ($warehouseGroups as $warehouse)
                            <section class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700/60 dark:bg-gray-900/30">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100">{{ $warehouse['warehouse_name'] }}</h3>
                                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Products are grouped by category and sorted alphabetically within each category.</p>
                                    </div>
                                    <div class="rounded-xl bg-gray-50 px-3 py-2 text-sm text-gray-600 dark:bg-gray-800/80 dark:text-gray-300">
                                        <div>{{ number_format($warehouse['subtotal_quantity']) }} units</div>
                                        <div class="mt-1">{{ $warehouse['subtotal_value_display'] }} value</div>
                                    </div>
                                </div>

                                @if (! $warehouse['has_inventory'])
                                    <div class="mt-4 rounded-2xl border border-dashed border-gray-300 bg-gray-50 px-6 py-4 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900/30 dark:text-gray-400">
                                        No inventory on hand for this warehouse.
                                    </div>
                                @else
                                    <div class="mt-4 space-y-4">
                                        @foreach ($warehouse['categories'] as $category)
                                            <section class="rounded-2xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700/60 dark:bg-gray-800/40">
                                                <div class="flex flex-wrap items-start justify-between gap-3">
                                                    <div>
                                                        <h4 class="text-base font-semibold text-gray-800 dark:text-gray-100">{{ \Illuminate\Support\Str::title($category['category']) }}</h4>
                                                    </div>
                                                    <div class="rounded-xl bg-white px-3 py-2 text-sm text-gray-600 dark:bg-gray-900/60 dark:text-gray-300">
                                                        <div>{{ number_format($category['subtotal_quantity']) }} units</div>
                                                        <div class="mt-1">{{ $category['subtotal_value_display'] }} value</div>
                                                    </div>
                                                </div>

                                                <div class="mt-4 overflow-x-auto">
                                                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700/60">
                                                        <thead class="bg-white dark:bg-gray-900/60">
                                                            <tr>
                                                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Product</th>
                                                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">SKU</th>
                                                                <th class="px-5 py-3 text-right font-medium text-gray-500 dark:text-gray-400">Qty On Hand</th>
                                                                <th class="px-5 py-3 text-right font-medium text-gray-500 dark:text-gray-400">Inventory Value</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700/60">
                                                            @foreach ($category['products'] as $product)
                                                                <tr class="bg-white dark:bg-gray-800">
                                                                    <td class="px-5 py-4 text-gray-800 dark:text-gray-100">
                                                                        <div class="font-medium">{{ $product['display_name'] }}</div>
                                                                        @if ($product['status'] === 'negative')
                                                                            <div class="mt-1 text-xs text-rose-700 dark:text-rose-300">Negative balance flagged for review.</div>
                                                                        @elseif ($product['status'] === 'zero')
                                                                            <div class="mt-1 text-xs text-amber-700 dark:text-amber-300">Zero on hand.</div>
                                                                        @endif
                                                                    </td>
                                                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $product['sku'] ?: '—' }}</td>
                                                                    <td class="px-5 py-4 text-right tabular-nums {{ $product['status'] === 'negative' ? 'text-rose-700 dark:text-rose-300' : ($product['status'] === 'zero' ? 'text-amber-700 dark:text-amber-300' : 'text-gray-800 dark:text-gray-100') }}">{{ number_format($product['quantity_on_hand']) }}</td>
                                                                    <td class="px-5 py-4 text-right tabular-nums text-gray-600 dark:text-gray-300">{{ $product['inventory_value_display'] }}</td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                        <tfoot class="bg-white dark:bg-gray-900/60">
                                                            <tr>
                                                                <th colspan="2" class="px-5 py-3 text-left font-semibold text-gray-800 dark:text-gray-100">Category Subtotal</th>
                                                                <th class="px-5 py-3 text-right tabular-nums font-semibold text-gray-900 dark:text-white">{{ number_format($category['subtotal_quantity']) }}</th>
                                                                <th class="px-5 py-3 text-right tabular-nums font-semibold text-gray-900 dark:text-white">{{ $category['subtotal_value_display'] }}</th>
                                                            </tr>
                                                        </tfoot>
                                                    </table>
                                                </div>
                                            </section>
                                        @endforeach
                                    </div>
                                @endif
                            </section>
                        @endforeach
                    </div>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
