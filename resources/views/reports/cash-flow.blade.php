<x-app-layout title="Cash Flow">
    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-5xl space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 md:text-3xl">Cash Flow</h1>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Cash in, operating outflows, inventory purchases, and commission payouts for the selected reporting window.</p>
            </div>

            <x-validation-errors />

            <section class="panel">
                <div class="panel-body border-b border-gray-200 dark:border-gray-700/60">
                    <form method="GET" action="{{ route('reports.cash-flow') }}" class="space-y-4">
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
                            <a href="{{ route('reports.cash-flow') }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Reset</a>
                        </div>
                    </form>
                </div>

                <div class="panel-body space-y-6">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Statement Summary</h2>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Reporting dates from {{ \App\Support\AppDateTime::displayDate($filters['date_from']) }} to {{ \App\Support\AppDateTime::displayDate($filters['date_to']) }}
                            </p>
                        </div>
                        <div class="rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 text-right dark:border-sky-500/30 dark:bg-sky-500/10">
                            <div class="text-xs font-medium uppercase tracking-wide text-sky-700 dark:text-sky-300">Net Cash Flow</div>
                            <div class="mt-1 text-2xl font-semibold text-sky-800 dark:text-sky-200">{{ $statement['net_cash_flow']['display'] }}</div>
                        </div>
                    </div>

                    @unless ($statement['has_activity'])
                        <div class="rounded-2xl border border-dashed border-gray-300 bg-gray-50 px-6 py-4 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900/30 dark:text-gray-400">
                            No cash activity was found in this date range. All values are shown as zero.
                        </div>
                    @endunless

                    <div class="space-y-6">
                        <section class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700/60 dark:bg-gray-900/30">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Cash Flow Lines</h3>

                            <div class="mt-4 overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700/60">
                                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700/60">
                                        <tr>
                                            <th class="px-0 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Cash In</th>
                                            <td class="px-0 py-3 text-right tabular-nums text-gray-800 dark:text-gray-100">{{ $statement['cash_in']['display'] }}</td>
                                        </tr>
                                        <tr>
                                            <th class="px-0 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Less: Expenses</th>
                                            <td class="px-0 py-3 text-right tabular-nums text-gray-800 dark:text-gray-100">{{ $statement['expenses']['display'] }}</td>
                                        </tr>
                                        <tr>
                                            <th class="px-0 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Less: Inventory Purchases</th>
                                            <td class="px-0 py-3 text-right tabular-nums text-gray-800 dark:text-gray-100">{{ $statement['inventory_purchases']['display'] }}</td>
                                        </tr>
                                        <tr>
                                            <th class="px-0 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Less: Commissions</th>
                                            <td class="px-0 py-3 text-right tabular-nums text-gray-800 dark:text-gray-100">{{ $statement['commissions']['display'] }}</td>
                                        </tr>
                                        <tr>
                                            <th class="px-0 py-3 text-left font-semibold text-gray-900 dark:text-white">Net Cash Flow</th>
                                            <td class="px-0 py-3 text-right tabular-nums text-xl font-semibold text-sky-800 dark:text-sky-200">{{ $statement['net_cash_flow']['display'] }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <section class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700/60 dark:bg-gray-900/30">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Commission Breakdown</h3>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Net-basis locations use location cash collected minus that location's own expenses, floored at $0 before the commission rate is applied.</p>
                                </div>
                                <div class="rounded-xl bg-gray-50 px-3 py-2 text-sm text-gray-600 dark:bg-gray-800/80 dark:text-gray-300">
                                    Total commissions: {{ $statement['commissions']['display'] }}
                                </div>
                            </div>

                            @if ($statement['commissions']['breakdown']->isEmpty())
                                <div class="mt-4 rounded-2xl border border-dashed border-gray-300 bg-gray-50 px-6 py-4 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900/30 dark:text-gray-400">
                                    No commission-enabled locations contributed to this period.
                                </div>
                            @else
                                <div class="mt-4 overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700/60">
                                        <thead class="bg-gray-50 dark:bg-gray-800/80">
                                            <tr>
                                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Location</th>
                                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Basis</th>
                                                <th class="px-5 py-3 text-right font-medium text-gray-500 dark:text-gray-400">Basis Amount</th>
                                                <th class="px-5 py-3 text-right font-medium text-gray-500 dark:text-gray-400">Rate</th>
                                                <th class="px-5 py-3 text-right font-medium text-gray-500 dark:text-gray-400">Commission</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700/60">
                                            @foreach ($statement['commissions']['breakdown'] as $commissionLine)
                                                <tr class="bg-white dark:bg-gray-800">
                                                    <td class="px-5 py-4 text-gray-800 dark:text-gray-100">
                                                        <div class="font-medium">{{ $commissionLine['location_name'] }}</div>
                                                        @if ($commissionLine['basis_was_floored'])
                                                            <div class="mt-1 text-xs text-amber-700 dark:text-amber-300">Basis floored at $0.00 after expenses exceeded sales.</div>
                                                        @endif
                                                    </td>
                                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ ucfirst($commissionLine['basis_type']) }}</td>
                                                    <td class="px-5 py-4 text-right tabular-nums text-gray-600 dark:text-gray-300">{{ $commissionLine['basis_display'] }}</td>
                                                    <td class="px-5 py-4 text-right tabular-nums text-gray-600 dark:text-gray-300">{{ $commissionLine['commission_rate_percent'] }}%</td>
                                                    <td class="px-5 py-4 text-right tabular-nums text-gray-800 dark:text-gray-100">{{ $commissionLine['commission_display'] }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </section>
                    </div>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
