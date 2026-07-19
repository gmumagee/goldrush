<?php

namespace App\Services;

use App\Models\ServiceSale;
use Illuminate\Support\Collection;

class ServiceSalesReportService
{
    public function productRollup(int $accountId, string $from, string $to): Collection
    {
        // Report only finalized sales rows so baseline setup services do not inflate product revenue.
        return ServiceSale::query()
            ->forAccount($accountId)
            ->where('calculation_status', ServiceSale::CALCULATION_CALCULATED)
            ->whereBetween('sales_date', [$from, $to])
            ->selectRaw('product_id, SUM(units_sold) AS total_units_sold, SUM(sales_amount) AS total_sales')
            ->groupBy('product_id')
            ->with('product')
            ->get();
    }

    public function machineRollup(int $accountId, string $from, string $to): Collection
    {
        // Keep machine rollups on persisted sales facts instead of recalculating from raw transactions.
        return ServiceSale::query()
            ->forAccount($accountId)
            ->where('calculation_status', ServiceSale::CALCULATION_CALCULATED)
            ->whereBetween('sales_date', [$from, $to])
            ->selectRaw('machine_id, SUM(units_sold) AS total_units_sold, SUM(sales_amount) AS total_sales')
            ->groupBy('machine_id')
            ->with('machine')
            ->get();
    }

    public function locationRollup(int $accountId, string $from, string $to): Collection
    {
        // Aggregate by historical location snapshot so later machine moves do not rewrite prior sales.
        return ServiceSale::query()
            ->forAccount($accountId)
            ->where('calculation_status', ServiceSale::CALCULATION_CALCULATED)
            ->whereBetween('sales_date', [$from, $to])
            ->selectRaw('location_id, SUM(units_sold) AS total_units_sold, SUM(sales_amount) AS total_sales')
            ->groupBy('location_id')
            ->with('location')
            ->get();
    }

    public function serviceTotal(int $accountId, int $serviceId): string
    {
        // Exclude baseline rows because they capture setup history, not measurable revenue.
        return (string) ServiceSale::query()
            ->forAccount($accountId)
            ->where('calculation_status', ServiceSale::CALCULATION_CALCULATED)
            ->where('service_id', $serviceId)
            ->sum('sales_amount');
    }
}
