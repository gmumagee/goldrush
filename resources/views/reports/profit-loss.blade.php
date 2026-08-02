<x-app-layout title="Profit & Loss">
    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-5xl space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 md:text-3xl">Profit &amp; Loss</h1>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Account-wide revenue, COGS, expenses, and net income for the selected reporting window.</p>
            </div>

            <x-validation-errors />

            <section class="panel">
                <div class="panel-body border-b border-gray-200 dark:border-gray-700/60">
                    <form method="GET" action="{{ route('reports.profit-loss') }}" class="space-y-4">
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
                            <a href="{{ route('reports.profit-loss') }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Reset</a>
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
                        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-right dark:border-emerald-500/30 dark:bg-emerald-500/10">
                            <div class="text-xs font-medium uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Net Income</div>
                            <div class="mt-1 text-2xl font-semibold text-emerald-800 dark:text-emerald-200">{{ $statement['net_income']['display'] }}</div>
                        </div>
                    </div>

                    @unless ($statement['has_activity'])
                        <div class="rounded-2xl border border-dashed border-gray-300 bg-gray-50 px-6 py-4 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900/30 dark:text-gray-400">
                            No revenue, sales, or expenses were found in this date range. All values are shown as zero.
                        </div>
                    @endunless

                    <div class="space-y-6">
                        <section class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700/60 dark:bg-gray-900/30">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Revenue</h3>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Net Income below uses <span class="font-medium text-gray-700 dark:text-gray-200">Actual Cash Collected</span>. Calculated Sales and Variance are diagnostic only.</p>
                                </div>
                                <div class="rounded-xl bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:bg-amber-500/10 dark:text-amber-200">
                                    Variance helps surface possible shrinkage or overage.
                                </div>
                            </div>

                            <div class="mt-4 overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700/60">
                                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700/60">
                                        <tr>
                                            <th class="px-0 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Actual Cash Collected</th>
                                            <td class="px-0 py-3 text-right tabular-nums text-gray-800 dark:text-gray-100">{{ $statement['actual_cash_collected']['display'] }}</td>
                                        </tr>
                                        <tr>
                                            <th class="px-0 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Calculated Sales</th>
                                            <td class="px-0 py-3 text-right tabular-nums text-gray-800 dark:text-gray-100">{{ $statement['calculated_sales']['display'] }}</td>
                                        </tr>
                                        <tr>
                                            <th class="px-0 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Variance (possible shrinkage/overage)</th>
                                            <td class="px-0 py-3 text-right tabular-nums font-medium {{ $statement['variance']['cents'] === 0 ? 'text-gray-800 dark:text-gray-100' : ($statement['variance']['cents'] > 0 ? 'text-amber-700 dark:text-amber-300' : 'text-blue-700 dark:text-blue-300') }}">{{ $statement['variance']['display'] }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <section class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700/60 dark:bg-gray-900/30">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Cost and Margin</h3>

                            <div class="mt-4 overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700/60">
                                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700/60">
                                        <tr>
                                            <th class="px-0 py-3 text-left font-medium text-gray-600 dark:text-gray-300">COGS</th>
                                            <td class="px-0 py-3 text-right tabular-nums text-gray-800 dark:text-gray-100">{{ $statement['cogs']['display'] }}</td>
                                        </tr>
                                        <tr>
                                            <th class="px-0 py-3 text-left font-semibold text-gray-800 dark:text-gray-100">Gross Profit</th>
                                            <td class="px-0 py-3 text-right tabular-nums font-semibold text-gray-900 dark:text-white">{{ $statement['gross_profit']['display'] }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <section class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700/60 dark:bg-gray-900/30">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Expenses</h3>

                            <div class="mt-4 overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700/60">
                                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700/60">
                                        @foreach ($statement['expenses']['breakdown'] as $expenseLine)
                                            <tr>
                                                <th class="px-0 py-3 text-left font-medium text-gray-600 dark:text-gray-300">{{ $expenseLine['label'] }}</th>
                                                <td class="px-0 py-3 text-right tabular-nums text-gray-800 dark:text-gray-100">{{ $expenseLine['display'] }}</td>
                                            </tr>
                                        @endforeach
                                        <tr>
                                            <th class="px-0 py-3 text-left font-semibold text-gray-800 dark:text-gray-100">Total Expenses</th>
                                            <td class="px-0 py-3 text-right tabular-nums font-semibold text-gray-900 dark:text-white">{{ $statement['expenses']['total_display'] }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <section class="rounded-2xl border border-violet-200 bg-violet-50 p-5 dark:border-violet-500/30 dark:bg-violet-500/10">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <h3 class="text-lg font-semibold text-violet-900 dark:text-violet-100">Net Income</h3>
                                    <p class="mt-1 text-sm text-violet-700 dark:text-violet-300">Calculated as Actual Cash Collected minus COGS minus Total Expenses.</p>
                                </div>
                                <div class="text-right">
                                    <div class="text-3xl font-bold text-violet-900 dark:text-violet-100">{{ $statement['net_income']['display'] }}</div>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
