<?php

namespace App\Services;

use App\Models\InventoryLedger;
use App\Models\Transaction;
use Illuminate\Support\Collection;

class InventoryCostService
{
    public function getAccountInventoryOnHand(int $accountId): Collection
    {
        return InventoryLedger::query()
            ->from('tbl_inventory_ledger as l')
            ->join('tbl_warehouses as w', function ($join) use ($accountId) {
                $join->on('w.id', '=', 'l.warehouse_id')
                    ->where('w.account_id', '=', $accountId);
            })
            ->join('tbl_products as p', function ($join) use ($accountId) {
                $join->on('p.id', '=', 'l.product_id')
                    ->where('p.account_id', '=', $accountId);
            })
            ->where('l.account_id', $accountId)
            ->groupBy(
                'l.warehouse_id',
                'w.warehouse_name',
                'l.product_id',
                'p.category',
                'p.sku',
                'p.product_name',
                'p.size',
                'p.package_type'
            )
            ->selectRaw('
                l.warehouse_id,
                w.warehouse_name,
                l.product_id,
                p.category,
                p.sku,
                p.product_name,
                p.size,
                p.package_type,
                COALESCE(SUM(l.quantity_delta), 0) as quantity_on_hand,
                COALESCE(SUM(l.total_cost), 0) as inventory_value,
                CASE
                    WHEN COALESCE(SUM(l.quantity_delta), 0) > 0
                    THEN ROUND(COALESCE(SUM(l.total_cost), 0) / SUM(l.quantity_delta), 4)
                    ELSE 0
                END as average_unit_cost
            ')
            ->orderBy('w.warehouse_name')
            ->orderBy('p.category')
            ->orderBy('p.product_name')
            ->get();
    }

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
