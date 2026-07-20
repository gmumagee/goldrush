<x-app-layout title="Dashboard">
    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-7xl space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 md:text-3xl">Dashboard</h1>
            </div>

            {{-- Use the shared Tailwind grid so the sales and low-inventory cards split the row responsively without fixed widths. --}}
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
                <div class="lg:col-span-8">
                    <section class="panel h-full" x-data='dashboardSalesChart(@json($salesChart))'>
                    <div class="panel-body border-b border-gray-200 dark:border-gray-700/60">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Sales</h2>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400" x-text="currentPeriod.title">Last 1 Month</p>
                            </div>
                        </div>
                    </div>

                    <div class="panel-body">
                        <div class="dashboard-sales-chart-container relative">
                            {{-- Render the sales series as SVG so the period buttons can swap datasets without reloading the page. --}}
                            <svg
                                data-sales-chart="line"
                                class="h-full w-full"
                                viewBox="0 0 760 320"
                                role="img"
                                aria-label="Sales by date for the last 1 month"
                                :aria-label="currentAriaLabel"
                            >
                                <text
                                    x="24"
                                    y="146"
                                    text-anchor="middle"
                                    transform="rotate(-90 24 146)"
                                    class="fill-gray-600 text-[12px] font-semibold dark:fill-gray-300"
                                >Sales (USD)</text>

                                <template x-for="tick in yTicks" :key="`y-${tick.label}`">
                                    <g>
                                        <line
                                            :x1="chartLeft"
                                            :x2="chartRight"
                                            :y1="tick.y"
                                            :y2="tick.y"
                                            class="stroke-gray-200 dark:stroke-gray-700/70"
                                            stroke-width="1"
                                        ></line>
                                        <text
                                            :x="chartLeft - 12"
                                            :y="tick.y + 4"
                                            text-anchor="end"
                                            class="fill-gray-500 text-[11px] dark:fill-gray-400"
                                            x-text="tick.label"
                                        ></text>
                                    </g>
                                </template>

                                <line
                                    :x1="chartLeft"
                                    :x2="chartLeft"
                                    :y1="padding.top"
                                    :y2="chartBottom"
                                    class="stroke-gray-300 dark:stroke-gray-600"
                                    stroke-width="1.5"
                                ></line>
                                <line
                                    :x1="chartLeft"
                                    :x2="chartRight"
                                    :y1="chartBottom"
                                    :y2="chartBottom"
                                    class="stroke-gray-300 dark:stroke-gray-600"
                                    stroke-width="1.5"
                                ></line>

                                <path
                                    :d="linePath"
                                    class="fill-none stroke-violet-600 dark:stroke-violet-300"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="3"
                                ></path>

                                <template x-for="point in points" :key="`point-${selectedPeriod}-${point.index}`">
                                    <g>
                                        <circle
                                            :cx="point.x"
                                            :cy="point.y"
                                            r="4"
                                            class="fill-white stroke-violet-600 dark:fill-gray-900 dark:stroke-violet-300"
                                            stroke-width="2"
                                            tabindex="0"
                                            @mouseenter="showTooltip(point)"
                                            @mouseleave="hideTooltip()"
                                            @focus="showTooltip(point)"
                                            @blur="hideTooltip()"
                                        ></circle>
                                    </g>
                                </template>

                                <template x-for="tick in xTicks" :key="`x-${selectedPeriod}-${tick.index}`">
                                    <g>
                                        <line
                                            :x1="tick.x"
                                            :x2="tick.x"
                                            :y1="chartBottom"
                                            :y2="chartBottom + 6"
                                            class="stroke-gray-300 dark:stroke-gray-600"
                                            stroke-width="1"
                                        ></line>
                                        <text
                                            :x="tick.x"
                                            :y="chartBottom + 22"
                                            text-anchor="middle"
                                            class="fill-gray-500 text-[11px] dark:fill-gray-400"
                                            x-text="tick.label"
                                        ></text>
                                    </g>
                                </template>

                                <text
                                    x="417"
                                    y="312"
                                    text-anchor="middle"
                                    class="fill-gray-600 text-[12px] font-semibold dark:fill-gray-300"
                                    x-text="currentPeriod.x_axis_label"
                                >Date</text>
                            </svg>

                            <div
                                x-show="tooltip.visible"
                                x-cloak
                                x-transition.opacity.duration.100ms
                                class="pointer-events-none absolute z-10 rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-medium text-gray-700 shadow-lg dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                                :style="`left: ${tooltip.xPercent}%; top: ${tooltip.yPercent}%; transform: translate(-50%, calc(-100% - 12px));`"
                            >
                                <div x-text="tooltip.label"></div>
                                <div class="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100" x-text="tooltip.value"></div>
                            </div>
                        </div>

                        <p
                            x-show="!currentPeriod.has_sales"
                            x-cloak
                            class="mt-3 text-center text-sm text-gray-500 dark:text-gray-400"
                        >
                            No calculated sales were recorded during this period.
                        </p>

                        <div class="mt-4 flex flex-wrap justify-center gap-2" role="group" aria-label="Select sales graph time period">
                            {{-- Keep the period controls below the chart so the graph remains the primary focus of the card. --}}
                            <button
                                type="button"
                                data-sales-period="1m"
                                aria-pressed="true"
                                class="inline-flex items-center rounded-xl border border-violet-600 bg-violet-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900"
                                :class="buttonClasses('1m')"
                                :aria-pressed="selectedPeriod === '1m' ? 'true' : 'false'"
                                @click="selectPeriod('1m')"
                            >
                                1 Month
                            </button>

                            <button
                                type="button"
                                data-sales-period="3m"
                                aria-pressed="false"
                                class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700 dark:focus:ring-offset-gray-900"
                                :class="buttonClasses('3m')"
                                :aria-pressed="selectedPeriod === '3m' ? 'true' : 'false'"
                                @click="selectPeriod('3m')"
                            >
                                3 Months
                            </button>

                            <button
                                type="button"
                                data-sales-period="6m"
                                aria-pressed="false"
                                class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700 dark:focus:ring-offset-gray-900"
                                :class="buttonClasses('6m')"
                                :aria-pressed="selectedPeriod === '6m' ? 'true' : 'false'"
                                @click="selectPeriod('6m')"
                            >
                                6 Months
                            </button>

                            <button
                                type="button"
                                data-sales-period="1y"
                                aria-pressed="false"
                                class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700 dark:focus:ring-offset-gray-900"
                                :class="buttonClasses('1y')"
                                :aria-pressed="selectedPeriod === '1y' ? 'true' : 'false'"
                                @click="selectPeriod('1y')"
                            >
                                1 Year
                            </button>
                        </div>
                    </div>
                    </section>
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
