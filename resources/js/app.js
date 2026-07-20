import Alpine from 'alpinejs';

window.Alpine = Alpine;

document.addEventListener('alpine:init', () => {
    Alpine.data('dashboardSalesChart', (chartData = {}) => ({
        chartData,
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
            top: 20,
            right: 20,
            bottom: 68,
            left: 74,
        },

        init() {
            // Fall back to the first available dataset so the chart never renders an invalid period key.
            if (! this.periods[this.selectedPeriod]) {
                this.selectedPeriod = Object.keys(this.periods)[0] ?? '1m';
            }
        },

        get periods() {
            return this.chartData?.periods ?? {};
        },

        get currentPeriod() {
            return this.periods[this.selectedPeriod] ?? {
                label: '',
                title: '',
                x_axis_label: 'Date',
                labels: [],
                tooltip_labels: [],
                values: [],
                has_sales: false,
            };
        },

        get currentLabels() {
            return Array.isArray(this.currentPeriod.labels)
                ? this.currentPeriod.labels
                : [];
        },

        get currentTooltipLabels() {
            return Array.isArray(this.currentPeriod.tooltip_labels)
                ? this.currentPeriod.tooltip_labels
                : [];
        },

        get currentValues() {
            // Normalize chart points to numbers once so the SVG math stays predictable.
            return Array.isArray(this.currentPeriod.values)
                ? this.currentPeriod.values.map((value) => Number(value) || 0)
                : [];
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

        get scaleMax() {
            return this.maxValue > 0 ? this.maxValue : 1;
        },

        get yTicks() {
            if (this.maxValue <= 0) {
                return [{
                    value: 0,
                    label: this.formatAxisCurrency(0),
                    y: this.chartBottom,
                }];
            }

            // Keep a fixed tick count so the chart stays readable across the four periods.
            const tickCount = 4;

            return Array.from({ length: tickCount + 1 }, (_, index) => {
                const value = (this.scaleMax / tickCount) * (tickCount - index);

                return {
                    value,
                    label: this.formatAxisCurrency(value),
                    y: this.padding.top + ((this.chartInnerHeight / tickCount) * index),
                };
            });
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

            if (this.points.length <= 6) {
                return this.points.map((point) => ({
                    index: point.index,
                    x: point.x,
                    label: point.label,
                }));
            }

            // Only render a subset of x-axis labels so full MM-DD-YYYY dates stay readable on smaller screens.
            const desiredTickCount = this.selectedPeriod === '1m' ? 6 : 7;
            const step = Math.max(1, Math.ceil((this.points.length - 1) / (desiredTickCount - 1)));
            const visibleIndexes = new Set([0, this.points.length - 1]);

            this.points.forEach((point) => {
                if (point.index % step === 0) {
                    visibleIndexes.add(point.index);
                }
            });

            return this.points
                .filter((point) => visibleIndexes.has(point.index))
                .map((point) => ({
                    index: point.index,
                    x: point.x,
                    label: point.label,
                }));
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

            return `Sales by ${xAxisLabel} for the ${title.toLowerCase()}`;
        },

        selectPeriod(periodKey) {
            // Switch datasets in place so the dashboard updates without a full page reload.
            if (! this.periods[periodKey]) {
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

        formatAxisCurrency(value) {
            const numericValue = Number(value) || 0;

            // Format y-axis ticks in explicit U.S. dollars without cents so the chart scale stays easy to scan.
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: 'USD',
                minimumFractionDigits: 0,
                maximumFractionDigits: 0,
            }).format(numericValue);
        },

        formatTooltipCurrency(value) {
            const numericValue = Number(value) || 0;

            // Keep hover values precise to cents while still using explicit U.S. dollar formatting.
            return `Sales: ${new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: 'USD',
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            }).format(numericValue)}`;
        },

        buttonClasses(periodKey) {
            if (this.selectedPeriod === periodKey) {
                return 'border-violet-600 bg-violet-600 text-white shadow-sm';
            }

            return 'border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700';
        },
    }));
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
