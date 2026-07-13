<?php

namespace App\Services;

use App\Models\InventoryLedger;
use App\Models\Transaction;

class InventoryCostService
{
    public function getWarehouseInventorySummary(int $accountId, int $warehouseId, int $productId): array
    {
        $inventory = InventoryLedger::query()
            ->where('account_id', $accountId)
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->selectRaw('
                COALESCE(SUM(quantity_delta), 0) as quantity_on_hand,
                COALESCE(SUM(total_cost), 0) as inventory_value
            ')
            ->first();

        $quantityOnHand = (int) ($inventory->quantity_on_hand ?? 0);
        $inventoryValue = round((float) ($inventory->inventory_value ?? 0), 4);

        return [
            'quantity_on_hand' => $quantityOnHand,
            'inventory_value' => $inventoryValue,
            'average_unit_cost' => $quantityOnHand > 0
                ? round($inventoryValue / $quantityOnHand, 4)
                : 0.0,
        ];
    }

    public function getCurrentAverageUnitCost(int $accountId, int $warehouseId, int $productId): float
    {
        return (float) $this->getWarehouseInventorySummary($accountId, $warehouseId, $productId)['average_unit_cost'];
    }

    public function getLastFillUnitCostForBin(int $accountId, int $binId, int $productId): ?float
    {
        $lastFill = Transaction::query()
            ->where('account_id', $accountId)
            ->where('bin_id', $binId)
            ->where('product_id', $productId)
            ->where('transaction_type', 'fill')
            ->orderByDesc('transaction_at')
            ->orderByDesc('id')
            ->first();

        if (! $lastFill) {
            return null;
        }

        return round((float) $lastFill->unit_cost, 4);
    }

    public function getUnitCostForCount(int $accountId, ?int $warehouseId, int $binId, ?int $productId): float
    {
        if (! $productId) {
            return 0.0;
        }

        $lastFillUnitCost = $this->getLastFillUnitCostForBin($accountId, $binId, $productId);

        if ($lastFillUnitCost !== null) {
            return $lastFillUnitCost;
        }

        if ($warehouseId) {
            return $this->getCurrentAverageUnitCost($accountId, $warehouseId, $productId);
        }

        return 0.0;
    }
}
