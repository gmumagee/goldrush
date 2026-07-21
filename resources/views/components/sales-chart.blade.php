@props([
    'chartId',
    'title',
    'chartData',
    'emptyMessage' => 'No calculated sales were recorded during this period.',
    'accessibleLabelPrefix' => 'Sales',
])

@php
    // Keep the chart DOM IDs unique per page so multiple sales graphs can coexist without conflicting accessibility hooks.
    $periodLabelId = $chartId.'-period-label';
    $emptyMessageId = $chartId.'-empty-message';
    $buttonGroupLabel = 'Select '.strtolower($accessibleLabelPrefix).' time period';
@endphp

<section
    class="panel h-full"
    x-data='salesLineChart(@json($chartData), @json([
        'accessibleLabelPrefix' => $accessibleLabelPrefix,
    ]))'
>
    <div class="panel-body border-b border-gray-200 dark:border-gray-700/60">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">{{ $title }}</h2>
                <p
                    id="{{ $periodLabelId }}"
                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                    x-text="currentPeriod.title"
                >Last 1 Month</p>
            </div>
        </div>
    </div>

    <div class="panel-body">
        <div class="dashboard-sales-chart-container relative">
            {{-- Reuse the same SVG renderer so every sales graph keeps identical axis, tooltip, and period-button behavior. --}}
            <svg
                id="{{ $chartId }}"
                data-sales-chart="line"
                data-sales-chart-id="{{ $chartId }}"
                class="h-full w-full"
                viewBox="0 0 760 320"
                role="img"
                aria-label="{{ $accessibleLabelPrefix }} by date for the last 1 month"
                :aria-label="currentAriaLabel"
                @mouseover="handlePointMouseOver($event)"
                @mouseout="handlePointMouseOut($event)"
                @focusin="handlePointFocusIn($event)"
                @focusout="handlePointFocusOut($event)"
            >
                <text
                    :x="padding.left - 64"
                    :y="padding.top + (chartInnerHeight / 2)"
                    text-anchor="middle"
                    :transform="`rotate(-90 ${padding.left - 64} ${padding.top + (chartInnerHeight / 2)})`"
                    class="fill-gray-600 text-[12px] font-semibold dark:fill-gray-300"
                >Sales (USD)</text>

                <g x-html="yAxisMarkup"></g>

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

                <g x-html="pointsMarkup"></g>
                <g x-html="xAxisMarkup"></g>

                <text
                    :x="chartLeft + (chartInnerWidth / 2)"
                    :y="chartBottom + 58"
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
            id="{{ $emptyMessageId }}"
            x-show="!currentPeriod.has_sales"
            x-cloak
            class="mt-3 text-center text-sm text-gray-500 dark:text-gray-400"
        >
            {{ $emptyMessage }}
        </p>

        <div class="mt-4 flex flex-wrap justify-center gap-2" role="group" aria-label="{{ $buttonGroupLabel }}">
            {{-- Keep one shared period-button layout so every sales chart uses the same selection styling and ordering. --}}
            <button
                type="button"
                data-sales-period="1m"
                data-sales-chart-id="{{ $chartId }}"
                aria-pressed="true"
                class="sales-period-button inline-flex items-center rounded-xl border px-4 py-2 text-sm font-medium transition focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900"
                :class="buttonClasses('1m')"
                :aria-pressed="selectedPeriod === '1m' ? 'true' : 'false'"
                @click="setSalesPeriod('1m')"
            >
                1 Month
            </button>

            <button
                type="button"
                data-sales-period="3m"
                data-sales-chart-id="{{ $chartId }}"
                aria-pressed="false"
                class="sales-period-button inline-flex items-center rounded-xl border px-4 py-2 text-sm font-medium transition focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900"
                :class="buttonClasses('3m')"
                :aria-pressed="selectedPeriod === '3m' ? 'true' : 'false'"
                @click="setSalesPeriod('3m')"
            >
                3 Months
            </button>

            <button
                type="button"
                data-sales-period="6m"
                data-sales-chart-id="{{ $chartId }}"
                aria-pressed="false"
                class="sales-period-button inline-flex items-center rounded-xl border px-4 py-2 text-sm font-medium transition focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900"
                :class="buttonClasses('6m')"
                :aria-pressed="selectedPeriod === '6m' ? 'true' : 'false'"
                @click="setSalesPeriod('6m')"
            >
                6 Months
            </button>

            <button
                type="button"
                data-sales-period="1y"
                data-sales-chart-id="{{ $chartId }}"
                aria-pressed="false"
                class="sales-period-button inline-flex items-center rounded-xl border px-4 py-2 text-sm font-medium transition focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900"
                :class="buttonClasses('1y')"
                :aria-pressed="selectedPeriod === '1y' ? 'true' : 'false'"
                @click="setSalesPeriod('1y')"
            >
                1 Year
            </button>
        </div>
    </div>
</section>
