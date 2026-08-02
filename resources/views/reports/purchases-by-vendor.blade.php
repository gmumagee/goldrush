<x-app-layout title="Purchases by Vendor">
    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-6xl space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 md:text-3xl">Purchases by Vendor</h1>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Vendor purchase totals with expandable purchase detail for the selected reporting window.</p>
            </div>

            <x-validation-errors />

            <section class="panel">
                <div class="panel-body border-b border-gray-200 dark:border-gray-700/60">
                    <form method="GET" action="{{ route('reports.purchases-by-vendor') }}" class="space-y-4">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="md:col-span-2">
                                <x-label for="vendor_id" value="Vendor" />
                                <select
                                    id="vendor_id"
                                    name="vendor_id"
                                    class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm transition focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                                >
                                    <option value="">All Vendors</option>
                                    @foreach ($vendors as $vendor)
                                        <option value="{{ $vendor->id }}" @selected($filters['vendor_id'] === (int) $vendor->id)>{{ $vendor->vendor_name }}</option>
                                    @endforeach
                                </select>
                            </div>

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
                            <a href="{{ route('reports.purchases-by-vendor') }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Reset</a>
                        </div>
                    </form>
                </div>

                <div class="panel-body space-y-6">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Vendor Summary</h2>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Reporting dates from {{ \App\Support\AppDateTime::displayDate($filters['date_from']) }} to {{ \App\Support\AppDateTime::displayDate($filters['date_to']) }}
                                @if ($selectedVendor)
                                    for {{ $selectedVendor->vendor_name }}
                                @endif
                            </p>
                        </div>
                        <div class="rounded-2xl border border-blue-200 bg-blue-50 px-4 py-3 text-right dark:border-blue-500/30 dark:bg-blue-500/10">
                            <div class="text-xs font-medium uppercase tracking-wide text-blue-700 dark:text-blue-300">Grand Total</div>
                            <div class="mt-1 text-2xl font-semibold text-blue-800 dark:text-blue-200">{{ $grandTotal }}</div>
                        </div>
                    </div>

                    @unless ($hasData)
                        <div class="rounded-2xl border border-dashed border-gray-300 bg-gray-50 px-6 py-4 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900/30 dark:text-gray-400">
                            No purchases in this date range.
                        </div>
                    @endunless

                    @if ($hasData)
                        <div x-data class="flex flex-wrap items-center justify-end gap-3">
                            <button
                                type="button"
                                class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                                @click="$dispatch('purchases-expand-all')"
                            >
                                Expand all
                            </button>
                            <button
                                type="button"
                                class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                                @click="$dispatch('purchases-collapse-all')"
                            >
                                Collapse all
                            </button>
                        </div>

                        <div class="space-y-4">
                            @foreach ($vendorGroups as $group)
                                @php($accordionId = 'vendor-purchase-panel-'.$loop->index)
                                <section
                                    class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700/60 dark:bg-gray-800"
                                    x-data="{ open: {{ $selectedVendor ? 'true' : 'false' }} }"
                                    x-on:purchases-expand-all.window="open = true"
                                    x-on:purchases-collapse-all.window="open = false"
                                >
                                    <button
                                        type="button"
                                        class="flex w-full items-center justify-between gap-4 px-5 py-4 text-left transition hover:bg-gray-50 dark:hover:bg-gray-700/30"
                                        @click="open = ! open"
                                        :aria-expanded="open.toString()"
                                        aria-controls="{{ $accordionId }}"
                                    >
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-3">
                                                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100">{{ $group['vendor_name'] }}</h3>
                                                <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600 dark:bg-gray-700/60 dark:text-gray-300">
                                                    {{ $group['purchase_count'] }} {{ \Illuminate\Support\Str::plural('purchase', $group['purchase_count']) }}
                                                </span>
                                            </div>
                                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Click to expand individual purchases for this vendor.</p>
                                        </div>

                                        <div class="flex items-center gap-4">
                                            <div class="text-right">
                                                <div class="text-sm text-gray-500 dark:text-gray-400">Total</div>
                                                <div class="text-lg font-semibold text-gray-900 dark:text-white">{{ $group['total_display'] }}</div>
                                            </div>
                                            <svg
                                                class="h-5 w-5 shrink-0 text-gray-400 transition-transform duration-200"
                                                :class="{ 'rotate-180': open }"
                                                viewBox="0 0 20 20"
                                                fill="currentColor"
                                                aria-hidden="true"
                                            >
                                                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.938a.75.75 0 1 1 1.08 1.04l-4.25 4.51a.75.75 0 0 1-1.08 0l-4.25-4.51a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                    </button>

                                    <div id="{{ $accordionId }}" x-cloak x-show="open" x-transition.opacity.duration.150ms class="border-t border-gray-200 dark:border-gray-700/60">
                                        <div class="overflow-x-auto">
                                            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700/60">
                                                <thead class="bg-gray-50 dark:bg-gray-800/80">
                                                    <tr>
                                                        <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Date</th>
                                                        <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Warehouse</th>
                                                        <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Status</th>
                                                        <th class="px-5 py-3 text-right font-medium text-gray-500 dark:text-gray-400">Amount</th>
                                                        <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700/60">
                                                    @foreach ($group['purchases'] as $purchase)
                                                        <tr class="bg-white dark:bg-gray-800">
                                                            <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $purchase['purchase_date_display'] }}</td>
                                                            <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $purchase['warehouse_name'] }}</td>
                                                            <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $purchase['status'] }}</td>
                                                            <td class="px-5 py-4 text-right tabular-nums text-gray-800 dark:text-gray-100">{{ $purchase['total_display'] }}</td>
                                                            <td class="px-5 py-4">
                                                                <a href="{{ route('purchases.show', $purchase['id']) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">View Purchase</a>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </section>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
