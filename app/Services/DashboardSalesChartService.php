<?php

namespace App\Services;

use App\Models\ServiceSale;
use App\Support\AppDateTime;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class DashboardSalesChartService
{
    public function buildForAccount(int $accountId): array
    {
        // Anchor every dashboard period to the configured app timezone so bucket labels stay stable.
        $today = CarbonImmutable::now((string) config('app.timezone', 'UTC'))->startOfDay();
        $oneYearStart = $today->subYear();
        $dailySales = $this->dailySalesTotals($accountId, $oneYearStart, $today);

        return [
            'default_period' => '1m',
            'periods' => [
                '1m' => $this->buildDailyPeriod(
                    $dailySales,
                    $today->subMonth(),
                    $today,
                    '1 Month',
                    'Last 1 Month',
                    'Date'
                ),
                '3m' => $this->buildWeeklyPeriod(
                    $dailySales,
                    $today->subMonths(3),
                    $today,
                    '3 Months',
                    'Last 3 Months',
                    'Week'
                ),
                '6m' => $this->buildWeeklyPeriod(
                    $dailySales,
                    $today->subMonths(6),
                    $today,
                    '6 Months',
                    'Last 6 Months',
                    'Week'
                ),
                '1y' => $this->buildMonthlyPeriod(
                    $dailySales,
                    $oneYearStart,
                    $today,
                    '1 Year',
                    'Last 1 Year',
                    'Month'
                ),
            ],
        ];
    }

    protected function dailySalesTotals(int $accountId, CarbonImmutable $startDate, CarbonImmutable $endDate): Collection
    {
        // Aggregate one year of daily revenue once so the dashboard can derive every chart period efficiently.
        return ServiceSale::query()
            ->where('account_id', $accountId)
            ->where('calculation_status', ServiceSale::CALCULATION_CALCULATED)
            ->whereNotNull('sales_amount')
            ->whereDate('sales_date', '>=', $startDate->toDateString())
            ->whereDate('sales_date', '<=', $endDate->toDateString())
            ->selectRaw('sales_date, SUM(sales_amount) AS total_sales')
            ->groupBy('sales_date')
            ->orderBy('sales_date')
            ->get()
            ->mapWithKeys(function ($row) {
                $salesDate = $row->sales_date instanceof CarbonInterface
                    ? $row->sales_date->toDateString()
                    : (string) $row->sales_date;

                return [$salesDate => Money::toCents($row->total_sales)];
            });
    }

    protected function buildDailyPeriod(
        Collection $dailySales,
        CarbonImmutable $startDate,
        CarbonImmutable $endDate,
        string $label,
        string $title,
        string $xAxisLabel,
    ): array {
        $labels = [];
        $tooltipLabels = [];
        $values = [];
        $hasSales = false;

        // Fill every day in the rolling window so the chart shows gaps as zero sales instead of missing data.
        for ($date = $startDate; $date->lte($endDate); $date = $date->addDay()) {
            $cents = (int) ($dailySales->get($date->toDateString()) ?? 0);

            $labels[] = AppDateTime::displayDate($date);
            $tooltipLabels[] = AppDateTime::displayDate($date);
            $values[] = $this->centsToChartValue($cents);
            $hasSales = $hasSales || $cents !== 0;
        }

        return [
            'label' => $label,
            'title' => $title,
            'x_axis_label' => $xAxisLabel,
            'labels' => $labels,
            'tooltip_labels' => $tooltipLabels,
            'values' => $values,
            'has_sales' => $hasSales,
        ];
    }

    protected function buildWeeklyPeriod(
        Collection $dailySales,
        CarbonImmutable $startDate,
        CarbonImmutable $endDate,
        string $label,
        string $title,
        string $xAxisLabel,
    ): array {
        $labels = [];
        $tooltipLabels = [];
        $values = [];
        $hasSales = false;

        // Use Sunday-based buckets so the sales chart matches the application's weekly calendar convention.
        for ($weekStart = $startDate->startOfWeek(CarbonInterface::SUNDAY); $weekStart->lte($endDate); $weekStart = $weekStart->addWeek()) {
            $weekEnd = $weekStart->endOfWeek(CarbonInterface::SATURDAY);
            $rangeStart = $weekStart->greaterThan($startDate) ? $weekStart : $startDate;
            $rangeEnd = $weekEnd->lessThan($endDate) ? $weekEnd : $endDate;
            $cents = $this->sumDateRange($dailySales, $rangeStart, $rangeEnd);

            $labels[] = AppDateTime::displayDate($weekStart);
            $tooltipLabels[] = 'Week of '.AppDateTime::displayDate($weekStart);
            $values[] = $this->centsToChartValue($cents);
            $hasSales = $hasSales || $cents !== 0;
        }

        return [
            'label' => $label,
            'title' => $title,
            'x_axis_label' => $xAxisLabel,
            'labels' => $labels,
            'tooltip_labels' => $tooltipLabels,
            'values' => $values,
            'has_sales' => $hasSales,
        ];
    }

    protected function buildMonthlyPeriod(
        Collection $dailySales,
        CarbonImmutable $startDate,
        CarbonImmutable $endDate,
        string $label,
        string $title,
        string $xAxisLabel,
    ): array {
        $labels = [];
        $tooltipLabels = [];
        $values = [];
        $hasSales = false;

        // Collapse long periods into month buckets so the one-year chart remains readable on the dashboard.
        for ($monthStart = $startDate->startOfMonth(); $monthStart->lte($endDate); $monthStart = $monthStart->addMonth()) {
            $monthEnd = $monthStart->endOfMonth();
            $rangeStart = $monthStart->greaterThan($startDate) ? $monthStart : $startDate;
            $rangeEnd = $monthEnd->lessThan($endDate) ? $monthEnd : $endDate;
            $cents = $this->sumDateRange($dailySales, $rangeStart, $rangeEnd);

            $labels[] = AppDateTime::displayDate($monthStart);
            $tooltipLabels[] = 'Month of '.AppDateTime::displayDate($monthStart);
            $values[] = $this->centsToChartValue($cents);
            $hasSales = $hasSales || $cents !== 0;
        }

        return [
            'label' => $label,
            'title' => $title,
            'x_axis_label' => $xAxisLabel,
            'labels' => $labels,
            'tooltip_labels' => $tooltipLabels,
            'values' => $values,
            'has_sales' => $hasSales,
        ];
    }

    protected function sumDateRange(Collection $dailySales, CarbonImmutable $startDate, CarbonImmutable $endDate): int
    {
        $cents = 0;

        // Sum pre-aggregated daily totals so weekly and monthly buckets stay accurate without extra queries.
        for ($date = $startDate; $date->lte($endDate); $date = $date->addDay()) {
            $cents += (int) ($dailySales->get($date->toDateString()) ?? 0);
        }

        return $cents;
    }

    protected function centsToChartValue(int $cents): float
    {
        // Convert integer cents once at the boundary so the frontend can plot stable decimal revenue values.
        return round($cents / 100, 2);
    }
}
