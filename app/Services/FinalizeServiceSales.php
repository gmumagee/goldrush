<?php

namespace App\Services;

use App\Models\Service;
use App\Models\ServiceSale;
use App\Models\Transaction;
use App\Support\Money;
use Carbon\CarbonInterface;

class FinalizeServiceSales
{
    public function __construct(protected ServiceSalesCalculator $calculator)
    {
    }

    public function finalize(Service $service, CarbonInterface $completedAt): array
    {
        // Keep the service completion workflow and persisted sales rows in sync.
        $result = [
            'sales_total_cents' => 0,
            'lines' => [],
            'warnings' => [],
            'errors' => [],
        ];

        if (! $service->isLocationService()) {
            $result['errors'][] = 'Sales reconciliation is only available for location services.';

            return $result;
        }

        if (! $service->account_id) {
            $result['errors'][] = 'The service is missing an account context.';

            return $result;
        }

        if ($service->isServiceClosed()) {
            $result['errors'][] = 'Closed services cannot be recalculated silently.';

            return $result;
        }

        $calculation = $this->calculator->calculate($service, $completedAt);

        if ($calculation['errors'] !== []) {
            return $calculation;
        }

        // Replace only this service's draft sales rows so retries stay idempotent before closure.
        ServiceSale::query()
            ->forAccount($service->account_id)
            ->where('service_id', $service->id)
            ->delete();

        $timestamp = now();
        $rows = collect($calculation['lines'])
            ->map(function (array $line) use ($timestamp) {
                return [
                    'account_id' => $line['account_id'],
                    'service_id' => $line['service_id'],
                    'location_id' => $line['location_id'],
                    'machine_id' => $line['machine_id'],
                    'bin_id' => $line['bin_id'],
                    'product_id' => $line['product_id'],
                    'previous_inventory_transaction_id' => $line['previous_inventory_transaction_id'],
                    'count_transaction_id' => $line['count_transaction_id'],
                    'calculation_status' => $line['calculation_status'],
                    'calculation_note' => $line['calculation_note'],
                    'sales_date' => $line['sales_date'],
                    'opening_quantity' => $line['opening_quantity'],
                    'inventory_additions' => $line['inventory_additions'],
                    'non_sale_removals' => $line['non_sale_removals'],
                    'counted_quantity' => $line['counted_quantity'],
                    'units_sold' => $line['units_sold'],
                    'unit_price' => Money::fromCents($line['unit_price_cents']),
                    'sales_amount' => $line['sales_amount_cents'] !== null
                        ? Money::fromCents($line['sales_amount_cents'])
                        : null,
                    'calculation_version' => ServiceSalesCalculator::CALCULATION_VERSION,
                    'calculated_at' => $timestamp,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            })
            ->all();

        if ($rows !== []) {
            ServiceSale::query()->insert($rows);
        }

        // Store the post-service inventory snapshot that the next reconciliation interval will open from.
        foreach ($calculation['lines'] as $line) {
            Transaction::query()->updateOrCreate(
                [
                    'account_id' => $service->account_id,
                    'service_id' => $service->id,
                    'machine_id' => $line['machine_id'],
                    'bin_id' => $line['bin_id'],
                    'product_id' => $line['product_id'],
                    'transaction_type' => Transaction::TYPE_CURRENT_INVENTORY,
                ],
                [
                    'quantity' => $line['closing_quantity'],
                    'price' => $line['closing_price'],
                    'unit_cost' => $line['closing_unit_cost'],
                    'transaction_at' => $completedAt,
                ]
            );
        }

        return $calculation;
    }
}
