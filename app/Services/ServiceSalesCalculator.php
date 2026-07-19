<?php

namespace App\Services;

use App\Models\Bin;
use App\Models\Machine;
use App\Models\Service;
use App\Models\ServiceSale;
use App\Models\Transaction;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class ServiceSalesCalculator
{
    public const CALCULATION_VERSION = 'inventory_reconciliation_v1';

    public function calculate(Service $service, ?CarbonInterface $completedAt = null): array
    {
        $completedAt ??= now();

        // Build one structured result so completion can fail without partial writes.
        $result = [
            'sales_total_cents' => 0,
            'lines' => [],
            'warnings' => [],
            'errors' => [],
        ];

        if (! $service->isLocationService()) {
            $result['warnings'][] = 'Maintenance services do not use inventory sales reconciliation.';

            return $result;
        }

        if (! $service->account_id) {
            $result['errors'][] = 'The service is missing an account context.';

            return $result;
        }

        $service->loadMissing('location');

        // Limit the reconciliation to machines and bins that actually belong to this location.
        $machineMap = Machine::query()
            ->where('account_id', $service->account_id)
            ->where('location_id', $service->location_id)
            ->get(['id', 'account_id', 'location_id', 'type', 'serial_number', 'model'])
            ->keyBy('id');

        $binMap = Bin::query()
            ->where('account_id', $service->account_id)
            ->whereIn('machine_id', $machineMap->keys())
            ->get(['id', 'account_id', 'machine_id', 'product_id', 'bin_code', 'price'])
            ->keyBy('id');

        $binsMissingProducts = $binMap
            ->filter(fn (Bin $bin) => $bin->product_id === null)
            ->map(fn (Bin $bin) => $this->describeBin($bin, (int) $bin->id))
            ->values()
            ->all();

        if ($binsMissingProducts !== []) {
            $result['errors'][] = 'The following bins do not have an assigned product: '.implode(', ', $binsMissingProducts).'.';
        }

        $expectedCountKeys = $binMap
            ->filter(fn (Bin $bin) => $bin->product_id !== null)
            ->mapWithKeys(fn (Bin $bin) => [$this->lineKey((int) $bin->id, (int) $bin->product_id) => $bin]);

        $countTransactions = Transaction::query()
            ->where('account_id', $service->account_id)
            ->where('service_id', $service->id)
            ->where('transaction_type', Transaction::TYPE_COUNT)
            ->orderBy('transaction_at')
            ->orderBy('id')
            ->get();

        $finalCountsByKey = $countTransactions
            ->filter(fn (Transaction $transaction) => $transaction->bin_id && $transaction->product_id)
            ->groupBy(fn (Transaction $transaction) => $this->lineKey((int) $transaction->bin_id, (int) $transaction->product_id))
            ->map(fn (Collection $transactions) => $transactions->last());

        $countTransactions
            ->filter(fn (Transaction $transaction) => $transaction->bin_id && $transaction->product_id)
            ->groupBy(fn (Transaction $transaction) => $this->lineKey((int) $transaction->bin_id, (int) $transaction->product_id))
            ->each(function (Collection $transactions, string $key) use (&$result, $binMap) {
                if ($transactions->count() < 2) {
                    return;
                }

                [$binId] = explode(':', $key);
                $result['warnings'][] = 'Multiple counts were recorded for '.$this->describeBin($binMap->get((int) $binId), (int) $binId).'. The latest count was used for sales reconciliation.';
            });

        $missingCountKeys = array_values(array_diff(
            $expectedCountKeys->keys()->all(),
            $finalCountsByKey->keys()->all(),
        ));

        if ($missingCountKeys !== []) {
            $result['errors'][] = 'Final counts are required before completing the service for bins: '
                .$this->describeMissingBins($missingCountKeys, $binMap).'.';
        }

        if ($finalCountsByKey->isEmpty()) {
            return $result;
        }

        // Load the full per-bin movement history once so every line can reconcile without N+1 queries.
        $historyByKey = Transaction::query()
            ->where('account_id', $service->account_id)
            ->whereIn('bin_id', $finalCountsByKey->pluck('bin_id')->filter()->unique()->values())
            ->whereIn('product_id', $finalCountsByKey->pluck('product_id')->filter()->unique()->values())
            ->whereIn('transaction_type', Transaction::movementTypesForSales())
            ->orderBy('bin_id')
            ->orderBy('product_id')
            ->orderBy('transaction_at')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Transaction $transaction) => $this->lineKey((int) $transaction->bin_id, (int) $transaction->product_id));

        foreach ($finalCountsByKey as $key => $finalCount) {
            $bin = $binMap->get($finalCount->bin_id);
            $machine = $finalCount->machine_id ? $machineMap->get($finalCount->machine_id) : null;

            if (! $finalCount->bin_id) {
                $result['errors'][] = 'A final count transaction is missing its bin reference.';
                continue;
            }

            if (! $finalCount->product_id) {
                $result['errors'][] = 'A final count transaction is missing its product reference.';
                continue;
            }

            if (! $finalCount->machine_id) {
                $result['errors'][] = 'A final count transaction is missing its machine reference.';
                continue;
            }

            if (! $machine || (int) $machine->location_id !== (int) $service->location_id) {
                $result['errors'][] = 'A counted machine does not belong to the service location.';
                continue;
            }

            $history = $historyByKey->get($key, collect());
            $previousInventoryTransaction = $history
                ->filter(fn (Transaction $transaction) => $transaction->transaction_type === Transaction::TYPE_CURRENT_INVENTORY)
                ->filter(fn (Transaction $transaction) => $this->isBefore($transaction, $finalCount))
                ->last();

            if ($previousInventoryTransaction && (int) $previousInventoryTransaction->account_id !== (int) $service->account_id) {
                $result['errors'][] = 'An opening inventory snapshot was found outside the current account scope.';
                continue;
            }

            $resolvedPrice = $this->resolveUnitPrice($previousInventoryTransaction, $finalCount, $bin);

            if ($resolvedPrice['unit_price'] === null) {
                $result['errors'][] = 'No selling price is available for '.$this->describeBin($bin, (int) $finalCount->bin_id).'.';
                continue;
            }

            if ($resolvedPrice['warning']) {
                $result['warnings'][] = $resolvedPrice['warning'];
            }

            $postCountMovements = $history->filter(function (Transaction $transaction) use ($finalCount, $completedAt) {
                if (! $this->isAfter($transaction, $finalCount)) {
                    return false;
                }

                if ($transaction->transaction_type === Transaction::TYPE_CURRENT_INVENTORY) {
                    return false;
                }

                return $transaction->transaction_at === null
                    || $transaction->transaction_at->lessThanOrEqualTo($completedAt);
            });

            // Count spoilage is stored on the final count and should not be mixed with the post-count restock interval.
            $spoilage = max(0, (int) ($finalCount->spoilage ?? 0));
            $closingAdditions = $this->sumPositiveMovements($postCountMovements);
            $closingRemovals = $this->sumNegativeMovements($postCountMovements);

            $countedQuantity = (int) $finalCount->quantity;
            $closingQuantity = $countedQuantity + $closingAdditions - $closingRemovals;

            if ($closingQuantity < 0) {
                $result['errors'][] = 'A negative closing inventory snapshot was calculated for '.$this->describeBin($bin, (int) $finalCount->bin_id).'.';
                continue;
            }

            $unitPriceCents = Money::toCents($resolvedPrice['unit_price']);
            $snapshotUnitCost = $this->resolveUnitCost($previousInventoryTransaction, $finalCount, $postCountMovements);

            if ($previousInventoryTransaction !== null) {
                // Reconcile revenue only when a prior current-inventory snapshot exists for the interval.
                $openingQuantity = (int) $previousInventoryTransaction->quantity;
                $unitsSold = $openingQuantity - $countedQuantity - $spoilage;

                if ($unitsSold < 0) {
                    $result['errors'][] = $this->describeBin($bin, (int) $finalCount->bin_id)
                        .' has a Count plus Spoilage greater than its opening inventory.';
                    continue;
                }

                $salesAmountCents = $unitsSold * $unitPriceCents;

                $result['lines'][] = [
                    'account_id' => (int) $service->account_id,
                    'service_id' => (int) $service->id,
                    'location_id' => (int) $service->location_id,
                    'machine_id' => (int) $finalCount->machine_id,
                    'bin_id' => (int) $finalCount->bin_id,
                    'product_id' => (int) $finalCount->product_id,
                    'previous_inventory_transaction_id' => (int) $previousInventoryTransaction->id,
                    'count_transaction_id' => (int) $finalCount->id,
                    'calculation_status' => ServiceSale::CALCULATION_CALCULATED,
                    'calculation_note' => null,
                    'sales_date' => $service->service_date?->toDateString() ?? $completedAt->toDateString(),
                    'opening_quantity' => $openingQuantity,
                    'spoilage' => $spoilage,
                    'counted_quantity' => $countedQuantity,
                    'units_sold' => $unitsSold,
                    'unit_price_cents' => $unitPriceCents,
                    'sales_amount_cents' => $salesAmountCents,
                    'closing_quantity' => $closingQuantity,
                    'closing_price' => $resolvedPrice['unit_price'],
                    'closing_unit_cost' => $snapshotUnitCost,
                ];

                $result['sales_total_cents'] += $salesAmountCents;

                continue;
            }

            // Persist a baseline line when this service establishes the first usable inventory snapshot.
            $result['lines'][] = [
                'account_id' => (int) $service->account_id,
                'service_id' => (int) $service->id,
                'location_id' => (int) $service->location_id,
                'machine_id' => (int) $finalCount->machine_id,
                'bin_id' => (int) $finalCount->bin_id,
                'product_id' => (int) $finalCount->product_id,
                'previous_inventory_transaction_id' => null,
                'count_transaction_id' => (int) $finalCount->id,
                'calculation_status' => ServiceSale::CALCULATION_BASELINE,
                'calculation_note' => 'Initial inventory baseline; no previous Current Inventory record was available.',
                'sales_date' => $service->service_date?->toDateString() ?? $completedAt->toDateString(),
                'opening_quantity' => null,
                'spoilage' => $spoilage,
                'counted_quantity' => $countedQuantity,
                'units_sold' => null,
                'unit_price_cents' => $unitPriceCents,
                'sales_amount_cents' => null,
                'closing_quantity' => $closingQuantity,
                'closing_price' => $resolvedPrice['unit_price'],
                'closing_unit_cost' => $snapshotUnitCost,
            ];

            $result['warnings'][] = $this->describeBin($bin, (int) $finalCount->bin_id)
                .' was initialized as an inventory baseline. Sales will be available after the next service.';
        }

        return $result;
    }

    protected function resolveUnitPrice(?Transaction $openingTransaction, Transaction $finalCount, ?Bin $bin): array
    {
        if ($openingTransaction?->price !== null) {
            return [
                'unit_price' => (string) $openingTransaction->price,
                'warning' => null,
            ];
        }

        if ($finalCount->price !== null) {
            return [
                'unit_price' => (string) $finalCount->price,
                'warning' => null,
            ];
        }

        if ($bin?->price !== null) {
            return [
                'unit_price' => (string) $bin->price,
                'warning' => 'Used the current bin price as the fallback selling price for '.$this->describeBin($bin, (int) $finalCount->bin_id).'.',
            ];
        }

        return [
            'unit_price' => null,
            'warning' => null,
        ];
    }

    protected function resolveUnitCost(?Transaction $openingTransaction, Transaction $finalCount, Collection $postCountMovements): ?string
    {
        $latestPostCountUnitCost = $postCountMovements
            ->filter(fn (Transaction $transaction) => $transaction->unit_cost !== null)
            ->last()?->unit_cost;

        $resolved = $latestPostCountUnitCost
            ?? $finalCount->unit_cost
            ?? $openingTransaction?->unit_cost;

        return $resolved !== null ? (string) $resolved : null;
    }

    protected function sumPositiveMovements(Collection $transactions): int
    {
        return $transactions->sum(function (Transaction $transaction) {
            $quantity = (int) $transaction->quantity;

            return match ($transaction->transaction_type) {
                Transaction::TYPE_FILL,
                Transaction::TYPE_ADD => $quantity,
                Transaction::TYPE_ADJUSTMENT => max(0, $quantity),
                default => 0,
            };
        });
    }

    protected function sumNegativeMovements(Collection $transactions): int
    {
        return $transactions->sum(function (Transaction $transaction) {
            $quantity = (int) $transaction->quantity;

            return match ($transaction->transaction_type) {
                Transaction::TYPE_WASTE,
                Transaction::TYPE_REMOVE => $quantity,
                Transaction::TYPE_ADJUSTMENT => abs(min(0, $quantity)),
                default => 0,
            };
        });
    }

    protected function isBefore(Transaction $candidate, Transaction $reference): bool
    {
        if ($candidate->transaction_at?->lt($reference->transaction_at)) {
            return true;
        }

        return $candidate->transaction_at?->equalTo($reference->transaction_at)
            && (int) $candidate->id < (int) $reference->id;
    }

    protected function isAfter(Transaction $candidate, Transaction $reference): bool
    {
        if ($candidate->transaction_at?->gt($reference->transaction_at)) {
            return true;
        }

        return $candidate->transaction_at?->equalTo($reference->transaction_at)
            && (int) $candidate->id > (int) $reference->id;
    }

    protected function lineKey(int $binId, int $productId): string
    {
        return $binId.':'.$productId;
    }

    protected function describeMissingBins(array $keys, Collection $binMap): string
    {
        return collect($keys)
            ->map(function (string $key) use ($binMap) {
                [$binId] = explode(':', $key);

                return $this->describeBin($binMap->get((int) $binId), (int) $binId);
            })
            ->implode(', ');
    }

    protected function describeBin(?Bin $bin, int $fallbackBinId): string
    {
        if (! $bin) {
            return 'bin #'.$fallbackBinId;
        }

        return $bin->bin_code ?: 'bin #'.$fallbackBinId;
    }
}
