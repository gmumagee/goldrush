<x-app-layout title="Inventory Consumed">
    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-5xl space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 md:text-3xl">Inventory Consumed</h1>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Calculated units consumed during servicing, grouped by product category and product, for the selected reporting window.</p>
            </div>

            <x-validation-errors />

            <section class="panel">
                <div class="panel-body border-b border-gray-200 dark:border-gray-700/60">
                    <form method="GET" action="{{ route('reports.inventory-consumed') }}" class="space-y-4">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <x-label for="date_from" value="From" />
                                <x-input id="date_from" name="date_from" type="date" :value="$filters['date_from']" />
                            </div>
                            <div>
                                <x-label for="date_to" value="To" />
                                <x-input id="date_to" name="date_to" type="date" :value="$filters['date_to']" />
                            </div>
                        </div>

                        <div class="flex gap-3">
                            <x-button>Filter</x-button>
                            <a href="{{ route('reports.inventory-consumed') }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Reset</a>
                        </div>
                    </form>
                </div>

                <div class="panel-body space-y-6">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Consumption Summary</h2>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Reporting dates from {{ \App\Support\AppDateTime::displayDate($filters['date_from']) }} to {{ \App\Support\AppDateTime::displayDate($filters['date_to']) }}
                            </p>
                        </div>
                        <div class="rounded-2xl border border-orange-200 bg-orange-50 px-4 py-3 text-right dark:border-orange-500/30 dark:bg-orange-500/10">
                            <div class="text-xs font-medium uppercase tracking-wide text-orange-700 dark:text-orange-300">Grand Total Units</div>
                            <div class="mt-1 text-2xl font-semibold text-orange-800 dark:text-orange-200">{{ number_format($grandTotalUnits) }}</div>
                        </div>
                    </div>

                    @unless ($hasData)
                        <div class="rounded-2xl border border-dashed border-gray-300 bg-gray-50 px-6 py-4 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900/30 dark:text-gray-400">
                            No inventory consumption in this date range.
                        </div>
                    @endunless

                    <div class="space-y-6">
                        @foreach ($categoryGroups as $group)
                            <section class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700/60 dark:bg-gray-900/30">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100">{{ \Illuminate\Support\Str::title($group['category']) }}</h3>
                                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Products are sorted alphabetically within each category.</p>
                                    </div>
                                    <div class="rounded-xl bg-gray-50 px-3 py-2 text-sm text-gray-600 dark:bg-gray-800/80 dark:text-gray-300">
                                        Category total: {{ number_format($group['subtotal_units']) }} units
                                    </div>
                                </div>

                                <div class="mt-4 overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700/60">
                                        <thead class="bg-gray-50 dark:bg-gray-800/80">
                                            <tr>
                                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Product</th>
                                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">SKU</th>
                                                <th class="px-5 py-3 text-right font-medium text-gray-500 dark:text-gray-400">Units Consumed</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700/60">
                                            @foreach ($group['products'] as $product)
                                                <tr class="bg-white dark:bg-gray-800">
                                                    <td class="px-5 py-4 text-gray-800 dark:text-gray-100">{{ $product['product_name'] }}</td>
                                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $product['sku'] }}</td>
                                                    <td class="px-5 py-4 text-right tabular-nums text-gray-800 dark:text-gray-100">{{ number_format($product['units_consumed']) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                        <tfoot class="bg-gray-50 dark:bg-gray-800/80">
                                            <tr>
                                                <th colspan="2" class="px-5 py-3 text-left font-semibold text-gray-800 dark:text-gray-100">Category Subtotal</th>
                                                <th class="px-5 py-3 text-right tabular-nums font-semibold text-gray-900 dark:text-white">{{ number_format($group['subtotal_units']) }}</th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </section>
                        @endforeach

                        <section class="rounded-2xl border border-orange-200 bg-orange-50 p-5 dark:border-orange-500/30 dark:bg-orange-500/10">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <h3 class="text-lg font-semibold text-orange-900 dark:text-orange-100">Grand Total</h3>
                                    <p class="mt-1 text-sm text-orange-700 dark:text-orange-300">Total units consumed across all included categories.</p>
                                </div>
                                <div class="text-right">
                                    <div class="text-3xl font-bold text-orange-900 dark:text-orange-100">{{ number_format($grandTotalUnits) }}</div>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
