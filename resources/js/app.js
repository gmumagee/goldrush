import Alpine from 'alpinejs';

window.Alpine = Alpine;

document.addEventListener('alpine:init', () => {
    const createSalesLineChart = (chartData = {}, chartMeta = {}) => ({
        chartData,
        chartMeta,
        selectedPeriod: chartData.default_period ?? '1m',
        tooltip: {
            visible: false,
            xPercent: 0,
            yPercent: 0,
            label: '',
            value: '',
        },
        chartWidth: 760,
        chartHeight: 320,
        padding: {
            top: 24,
            right: 24,
            bottom: 82,
            left: 92,
        },

        init() {
            // Initialize the chart through the same path used by the period buttons so labels and button state stay synchronized.
            const initialPeriodKey = this.periodConfigurations[this.selectedPeriod]
                ? this.selectedPeriod
                : Object.keys(this.periodConfigurations)[0] ?? '1m';

            this.setSalesPeriod(initialPeriodKey);
        },

        get periods() {
            return this.chartData?.periods ?? {};
        },

        get periodConfigurations() {
            return {
                '1m': this.buildPeriodConfiguration('1m', 'Date', 'MM-DD', 7),
                '3m': this.buildPeriodConfiguration('3m', 'Week', 'MM-DD', 7),
                '6m': this.buildPeriodConfiguration('6m', 'Week', 'MM-DD', 7),
                '1y': this.buildPeriodConfiguration('1y', 'Month', 'MM-YY', 13),
            };
        },

        get currentPeriod() {
            return this.periodConfigurations[this.selectedPeriod] ?? {
                label: '',
                title: '',
                x_axis_label: 'Date',
                tickFormat: 'MM-DD',
                maxXTicks: 8,
                labels: [],
                tooltipLabels: [],
                values: [],
                has_sales: false,
            };
        },

        buildPeriodConfiguration(periodKey, xAxisTitle, tickFormat, maxXTicks) {
            const period = this.periods[periodKey] ?? {};
            const labels = Array.isArray(period.labels) ? period.labels : [];
            const tooltipLabels = Array.isArray(period.tooltip_labels)
                ? period.tooltip_labels
                : labels;
            const values = Array.isArray(period.values)
                ? period.values.map((value) => {
                    const numericValue = Number(value);

                    return Number.isFinite(numericValue) ? numericValue : 0;
                })
                : [];

            return {
                periodKey,
                label: period.label ?? '',
                title: period.title ?? '',
                x_axis_label: xAxisTitle,
                tickFormat,
                maxXTicks,
                labels,
                tooltipLabels,
                values,
                has_sales: Boolean(period.has_sales),
            };
        },

        get currentLabels() {
            return Array.isArray(this.currentPeriod.labels)
                ? this.currentPeriod.labels
                : [];
        },

        get currentTooltipLabels() {
            return Array.isArray(this.currentPeriod.tooltipLabels)
                ? this.currentPeriod.tooltipLabels
                : [];
        },

        get currentValues() {
            // Normalize chart points to numbers once so the SVG math stays predictable.
            return this.normalizeSalesValues(this.currentPeriod.values);
        },

        get chartLeft() {
            return this.padding.left;
        },

        get chartRight() {
            return this.chartWidth - this.padding.right;
        },

        get chartBottom() {
            return this.chartHeight - this.padding.bottom;
        },

        get chartInnerWidth() {
            return this.chartRight - this.chartLeft;
        },

        get chartInnerHeight() {
            return this.chartBottom - this.padding.top;
        },

        get maxValue() {
            return this.currentValues.reduce(
                (carry, value) => Math.max(carry, value),
                0,
            );
        },

        get yAxisScale() {
            return this.buildYAxisScale(this.currentValues);
        },

        get scaleMax() {
            return this.yAxisScale.maximum;
        },

        normalizeSalesValues(values) {
            if (! Array.isArray(values)) {
                return [];
            }

            return values.map((value) => {
                const numericValue = Number(value);

                return Number.isFinite(numericValue) ? numericValue : 0;
            });
        },

        calculateNiceStep(maximumValue, intervalCount = 5) {
            if (! Number.isFinite(maximumValue) || maximumValue <= 0) {
                return 2;
            }

            const roughStep = maximumValue / intervalCount;
            const magnitude = 10 ** Math.floor(Math.log10(roughStep));
            const normalizedStep = roughStep / magnitude;

            if (normalizedStep <= 1) {
                return 1 * magnitude;
            }

            if (normalizedStep <= 2) {
                return 2 * magnitude;
            }

            if (normalizedStep <= 5) {
                return 5 * magnitude;
            }

            return 10 * magnitude;
        },

        buildYAxisScale(values) {
            const numericValues = this.normalizeSalesValues(values);
            const maximumValue = Math.max(0, ...numericValues);
            const intervalCount = 5;
            const step = this.calculateNiceStep(maximumValue, intervalCount);
            const maximum = Math.max(
                step * intervalCount,
                Math.ceil(maximumValue / step) * step,
            );
            const ticks = [];

            for (let value = 0; value <= maximum; value += step) {
                ticks.push(value);
            }

            return {
                minimum: 0,
                maximum,
                step,
                ticks,
            };
        },

        get yTicks() {
            const scale = this.yAxisScale;

            return scale.ticks.map((value) => ({
                value,
                label: this.formatAxisCurrency(value),
                y: this.chartBottom - (((value - scale.minimum) / (scale.maximum - scale.minimum || 1)) * this.chartInnerHeight),
            }));
        },

        get points() {
            if (! this.currentValues.length) {
                return [];
            }

            const denominator = Math.max(this.currentValues.length - 1, 1);

            return this.currentValues.map((value, index) => {
                const x = this.chartLeft + ((this.chartInnerWidth * index) / denominator);
                const y = this.chartBottom - ((value / this.scaleMax) * this.chartInnerHeight);

                return {
                    index,
                    x,
                    y,
                    xPercent: (x / this.chartWidth) * 100,
                    yPercent: (y / this.chartHeight) * 100,
                    label: this.currentLabels[index] ?? '',
                    tooltipLabel: this.currentTooltipLabels[index] ?? this.currentLabels[index] ?? '',
                    value,
                };
            });
        },

        get xTicks() {
            if (! this.points.length) {
                return [];
            }

            const maxTicks = this.currentPeriod.maxXTicks ?? 8;
            const visibleIndexes = this.selectVisibleTickIndexes(this.points.length, maxTicks);

            return this.points
                .filter((point) => visibleIndexes.includes(point.index))
                .map((point) => ({
                    index: point.index,
                    x: point.x,
                    label: this.formatSalesXAxisTick(point.label, this.currentPeriod.periodKey),
                }));
        },

        parseDateOnly(value) {
            const match = String(value ?? '').match(/^(\d{4})-(\d{2})-(\d{2})$/);

            if (! match) {
                return null;
            }

            const [, year, month, day] = match;

            return new Date(
                Number(year),
                Number(month) - 1,
                Number(day),
            );
        },

        formatSalesXAxisTick(rawValue, periodKey) {
            const date = this.parseDateOnly(rawValue);

            if (! date) {
                return rawValue;
            }

            const month = String(date.getMonth() + 1).padStart(2, '0');

            if (periodKey === '1y') {
                const year = String(date.getFullYear()).slice(-2);

                return `${month}-${year}`;
            }

            const day = String(date.getDate()).padStart(2, '0');

            return `${month}-${day}`;
        },

        selectVisibleTickIndexes(itemCount, maximumTicks) {
            if (itemCount <= 0) {
                return [];
            }

            if (itemCount <= maximumTicks) {
                return Array.from({ length: itemCount }, (_, index) => index);
            }

            const indexes = new Set([0, itemCount - 1]);
            const interval = (itemCount - 1) / (maximumTicks - 1);

            for (let tick = 1; tick < maximumTicks - 1; tick += 1) {
                indexes.add(Math.round(tick * interval));
            }

            return [...indexes].sort((left, right) => left - right);
        },

        get linePath() {
            if (! this.points.length) {
                return '';
            }

            return this.points
                .map((point, index) => `${index === 0 ? 'M' : 'L'} ${point.x} ${point.y}`)
                .join(' ');
        },

        get currentAriaLabel() {
            const title = this.currentPeriod.title || this.currentPeriod.label || 'selected period';
            const xAxisLabel = (this.currentPeriod.x_axis_label || 'date').toLowerCase();
            const accessibleLabelPrefix = this.chartMeta?.accessibleLabelPrefix || 'Sales';

            return `${accessibleLabelPrefix} by ${xAxisLabel} for the ${title.toLowerCase()}`;
        },

        get yAxisMarkup() {
            return this.yTicks.map((tick) => `
                <g>
                    <line
                        x1="${this.chartLeft}"
                        x2="${this.chartRight}"
                        y1="${tick.y}"
                        y2="${tick.y}"
                        class="stroke-gray-200 dark:stroke-gray-700/70"
                        stroke-width="1"
                    ></line>
                    <text
                        x="${this.chartLeft - 12}"
                        y="${tick.y + 4}"
                        text-anchor="end"
                        class="fill-gray-500 text-[11px] dark:fill-gray-400"
                    >${this.escapeSvgText(tick.label)}</text>
                </g>
            `).join('');
        },

        get xAxisMarkup() {
            return this.xTicks.map((tick) => `
                <g>
                    <line
                        x1="${tick.x}"
                        x2="${tick.x}"
                        y1="${this.chartBottom}"
                        y2="${this.chartBottom + 6}"
                        class="stroke-gray-300 dark:stroke-gray-600"
                        stroke-width="1"
                    ></line>
                    <text
                        x="${tick.x}"
                        y="${this.chartBottom + 24}"
                        text-anchor="middle"
                        class="fill-gray-500 text-[11px] dark:fill-gray-400"
                    >${this.escapeSvgText(tick.label)}</text>
                </g>
            `).join('');
        },

        get pointsMarkup() {
            return this.points.map((point) => `
                <circle
                    cx="${point.x}"
                    cy="${point.y}"
                    r="4"
                    class="fill-white stroke-violet-600 dark:fill-gray-900 dark:stroke-violet-300"
                    stroke-width="2"
                    tabindex="0"
                    data-point-index="${point.index}"
                    aria-label="${this.escapeSvgAttribute(`${point.tooltipLabel}: ${this.formatTooltipCurrencyValue(point.value)}`)}"
                ></circle>
            `).join('');
        },

        setSalesPeriod(periodKey) {
            const period = this.periodConfigurations[periodKey];

            if (! period) {
                return;
            }

            if (period.labels.length !== period.values.length) {
                console.error('Sales chart labels and values do not match.', {
                    periodKey,
                    labels: period.labels.length,
                    values: period.values.length,
                });

                return;
            }

            this.selectedPeriod = periodKey;
            this.hideTooltip();
        },

        showTooltip(point) {
            // Keep tooltip content aggregated and presentation-safe instead of exposing raw sales rows.
            this.tooltip = {
                visible: true,
                xPercent: point.xPercent,
                yPercent: point.yPercent,
                label: point.tooltipLabel,
                value: this.formatTooltipCurrency(point.value),
            };
        },

        hideTooltip() {
            this.tooltip.visible = false;
        },

        handlePointMouseOver(event) {
            this.showTooltipFromEvent(event);
        },

        handlePointMouseOut(event) {
            if (! event.target?.matches?.('[data-point-index]')) {
                return;
            }

            this.hideTooltip();
        },

        handlePointFocusIn(event) {
            this.showTooltipFromEvent(event);
        },

        handlePointFocusOut(event) {
            if (! event.target?.matches?.('[data-point-index]')) {
                return;
            }

            this.hideTooltip();
        },

        showTooltipFromEvent(event) {
            const pointIndex = Number(event.target?.dataset?.pointIndex);

            if (! Number.isInteger(pointIndex)) {
                return;
            }

            const point = this.points[pointIndex];

            if (! point) {
                return;
            }

            this.showTooltip(point);
        },

        escapeSvgText(value) {
            return String(value ?? '')
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;');
        },

        escapeSvgAttribute(value) {
            return this.escapeSvgText(value)
                .replaceAll('"', '&quot;');
        },

        formatAxisCurrency(value) {
            const numericValue = Number(value) || 0;

            // Format y-axis ticks in explicit U.S. dollars while only showing cents when the dynamic step requires them.
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: 'USD',
                minimumFractionDigits: 0,
                maximumFractionDigits: 2,
            }).format(numericValue);
        },

        formatTooltipCurrencyValue(value) {
            const numericValue = Number(value) || 0;

            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: 'USD',
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            }).format(numericValue);
        },

        formatTooltipCurrency(value) {
            // Keep hover values precise to cents while still using explicit U.S. dollar formatting.
            return `Sales: ${this.formatTooltipCurrencyValue(value)}`;
        },

        buttonClasses(periodKey) {
            if (this.selectedPeriod === periodKey) {
                return 'active border-violet-600 bg-violet-600 text-white shadow-sm';
            }

            return 'border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700';
        },
    });

    // Register one reusable sales-chart state factory so the dashboard and location detail page stay behaviorally identical.
    Alpine.data('salesLineChart', createSalesLineChart);
    Alpine.data('dashboardSalesChart', createSalesLineChart);
});

// Bootstrap-style tooltip hooks stay safe here because the initializer exits when Bootstrap is not present.
document.addEventListener('DOMContentLoaded', () => {
    if (! window.bootstrap?.Tooltip) {
        return;
    }

    document
        .querySelectorAll('[data-bs-toggle="tooltip"]')
        .forEach((element) => {
            window.bootstrap.Tooltip.getOrCreateInstance(element);
        });
});

Alpine.start();
