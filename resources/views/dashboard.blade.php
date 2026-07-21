<x-app-layout title="Dashboard">
    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-7xl space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 md:text-3xl">Dashboard</h1>
            </div>

            {{-- Use the shared Tailwind grid so the sales and low-inventory cards split the row responsively without fixed widths. --}}
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
                <div class="lg:col-span-8">
                    <x-sales-chart
                        chart-id="dashboard-sales-chart"
                        title="Sales"
                        :chart-data="$salesChart"
                        empty-message="No calculated sales were recorded during this period."
                        accessible-label-prefix="Sales"
                    />
                </div>

                <div class="lg:col-span-4">
                    <section class="panel dashboard-low-inventory-card h-full">
                        <div class="panel-body border-b border-gray-200 dark:border-gray-700/60">
                            <h2 class="font-semibold text-gray-800 dark:text-gray-100">Low Inventory — Main Warehouse</h2>
                        </div>

                        <div class="panel-body p-0">
                            @if ($mainWarehouseInventoryState === 'missing')
                                <div class="p-3 text-gray-500 dark:text-gray-400">Main Warehouse is not configured for this account.</div>
                            @elseif ($mainWarehouseInventoryState === 'duplicate')
                                <div class="p-3 text-amber-700 dark:text-amber-300">Multiple Main Warehouse records are configured for this account.</div>
                            @elseif ($mainWarehouseInventoryState === 'no_inventory_data')
                                <div class="p-3 text-gray-500 dark:text-gray-400">No inventory data is available for Main Warehouse.</div>
                            @else
                                @foreach ($mainWarehouseLowInventoryProducts as $product)
                                    {{-- Keep each row compact so the right-side card still reads cleanly without extra fields. --}}
                                    <div class="low-inventory-row">
                                        <span class="low-inventory-product" title="{{ $product->product_name }}">
                                            {{ $product->product_name }}
                                        </span>

                                        <span class="low-inventory-quantity">
                                            {{ number_format((int) $product->quantity_on_hand) }}
                                        </span>
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    </section>
                </div>
            </div>

            @include('calendar-events._week', [
                'title' => 'Weekly Calendar',
                'weekStart' => $weekStart,
                'weekEnd' => $weekEnd,
                'weekDays' => $weekDays,
                'eventsByDate' => $eventsByDate,
                'previousWeekUrl' => route('dashboard', ['date' => $weekStart->copy()->subWeek()->toDateString()]),
                'currentWeekUrl' => route('dashboard', ['date' => now()->toDateString()]),
                'nextWeekUrl' => route('dashboard', ['date' => $weekStart->copy()->addWeek()->toDateString()]),
                'emptyDayText' => 'No scheduled events.',
            ])

            @if (session('status'))
                <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-900/60 dark:bg-green-500/10 dark:text-green-300">{{ session('status') }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
