<?php

namespace App\Services;

use App\Models\InventoryLedger;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Service;
use App\Models\Transaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WarehouseInventoryService
{
    public function __construct(protected InventoryCostService $inventoryCostService)
    {
    }

    public function postPurchase(array $purchaseData, array $items): Purchase
    {
        // Purchase posting, purchase items, and ledger rows must commit
        // together so warehouse inventory never diverges from purchase history.
        return DB::transaction(function () use ($purchaseData, $items) {
            $purchase = Purchase::create($purchaseData);

            foreach ($items as $itemData) {
                $quantity = (int) $itemData['quantity'];
                $lineTotal = round((float) $itemData['line_total'], 2);
                $unitCost = $quantity > 0 ? round($lineTotal / $quantity, 4) : 0.0;

                $purchaseItem = PurchaseItem::create([
                    'account_id' => $purchase->account_id,
                    'purchase_id' => $purchase->id,
                    'product_id' => (int) $itemData['product_id'],
                    'quantity' => $quantity,
                    'line_total' => $lineTotal,
                    'unit_cost' => $unitCost,
                ]);

                InventoryLedger::create([
                    'account_id' => $purchase->account_id,
                    'warehouse_id' => $purchase->warehouse_id,
                    'product_id' => $purchaseItem->product_id,
                    'movement_type' => InventoryLedger::MOVEMENT_TYPE_PURCHASE,
                    'quantity_delta' => $quantity,
                    'unit_cost' => $unitCost,
                    'total_cost' => round($lineTotal, 4),
                    'source_type' => 'purchase_item',
                    'source_id' => $purchaseItem->id,
                    'movement_at' => $purchase->purchase_date,
                    'notes' => 'Posted purchase #'.$purchase->id,
                ]);
            }

            return $purchase->load(['items.product', 'vendor', 'warehouse']);
        });
    }

    public function voidPurchase(Purchase $purchase): void
    {
        // Void operations are append-only reversals so audit history remains
        // intact even when a posted purchase was entered incorrectly.
        DB::transaction(function () use ($purchase) {
            if (! $purchase->isPosted()) {
                throw ValidationException::withMessages([
                    'purchase' => 'Only posted purchases can be voided.',
                ]);
            }

            $aggregatedItems = $purchase->items
                ->groupBy('product_id')
                ->map(fn (Collection $items) => [
                    'quantity' => $items->sum('quantity'),
                ]);

            foreach ($aggregatedItems as $productId => $summary) {
                $snapshot = $this->snapshot($purchase->account_id, $purchase->warehouse_id, (int) $productId);

                if ($snapshot['quantity_on_hand'] < (int) $summary['quantity']) {
                    throw ValidationException::withMessages([
                        'purchase' => 'This purchase cannot be voided because some of the inventory has already been used.',
                    ]);
                }
            }

            foreach ($purchase->items as $item) {
                InventoryLedger::create([
                    'account_id' => $purchase->account_id,
                    'warehouse_id' => $purchase->warehouse_id,
                    'product_id' => $item->product_id,
                    'movement_type' => InventoryLedger::MOVEMENT_TYPE_PURCHASE_VOID,
                    'quantity_delta' => -1 * (int) $item->quantity,
                    'unit_cost' => round((float) $item->unit_cost, 4),
                    'total_cost' => -1 * round((float) $item->line_total, 4),
                    'source_type' => 'purchase_item',
                    'source_id' => $item->id,
                    'movement_at' => now(),
                    'notes' => 'Voided purchase #'.$purchase->id,
                ]);
            }

            $purchase->update([
                'status' => Purchase::STATUS_VOIDED,
            ]);
        });
    }

    public function createFillTransaction(Service $service, array $binPayloads): void
    {
        if (! $service->isLocationService()) {
            throw ValidationException::withMessages([
                'service' => 'Inventory transactions are only available for location services.',
            ]);
        }

        if (! $service->isServiceOpen()) {
            throw ValidationException::withMessages([
                'service' => 'Warehouse inventory can only be consumed while the service is Service Open.',
            ]);
        }

        if (! $service->warehouse_id) {
            throw ValidationException::withMessages([
                'service' => 'A source warehouse is required before machine fills can be recorded.',
            ]);
        }

        $binPayloads = collect($binPayloads)
            ->filter(fn (array $payload) => (int) $payload['quantity'] > 0)
            ->values();

        if ($binPayloads->isEmpty()) {
            return;
        }

        $payloadWithoutProduct = $binPayloads->first(fn (array $payload) => empty($payload['product_id']));

        if ($payloadWithoutProduct) {
            $binCode = $payloadWithoutProduct['bin_code'] ?? 'the selected bin';

            throw ValidationException::withMessages([
                'quantity' => 'A product must be assigned to '.$binCode.' before a fill can be recorded.',
            ]);
        }

        // Require the count step first so fill inventory only updates the next opening snapshot.
        $countedBinIds = Transaction::query()
            ->where('account_id', $service->account_id)
            ->where('service_id', $service->id)
            ->where('transaction_type', Transaction::TYPE_COUNT)
            ->whereIn('bin_id', $binPayloads->pluck('bin_id')->unique()->all())
            ->pluck('bin_id')
            ->map(fn ($binId) => (int) $binId)
            ->all();

        $firstMissingCount = $binPayloads->first(
            fn (array $payload) => ! in_array((int) $payload['bin_id'], $countedBinIds, true)
        );

        if ($firstMissingCount !== null) {
            $binCode = $firstMissingCount['bin_code'] ?? 'the selected bin';

            throw ValidationException::withMessages([
                'quantity' => 'Record the bin count before adding fill inventory for '.$binCode.'.',
            ]);
        }

        $aggregatedByProduct = $binPayloads
            ->groupBy('product_id')
            ->map(fn (Collection $payloads) => $payloads->sum('quantity'));

        foreach ($aggregatedByProduct as $productId => $quantityNeeded) {
            $snapshot = $this->snapshot($service->account_id, (int) $service->warehouse_id, (int) $productId);

            if ($snapshot['quantity_on_hand'] <= 0 || $snapshot['quantity_on_hand'] < (int) $quantityNeeded) {
                $productName = $binPayloads->firstWhere('product_id', (int) $productId)['product_name'] ?? 'this product';

                throw ValidationException::withMessages([
                    'quantity' => 'Not enough warehouse inventory for '.$productName.'.',
                ]);
            }
        }

        DB::transaction(function () use ($service, $binPayloads) {
            foreach ($binPayloads as $payload) {
                $snapshot = $this->snapshot($service->account_id, (int) $service->warehouse_id, (int) $payload['product_id']);
                $averageUnitCost = $snapshot['average_unit_cost'];
                $quantity = (int) $payload['quantity'];
                $transactionAt = $payload['transaction_at'] ?? now();

                $transaction = Transaction::create([
                    'account_id' => $service->account_id,
                    'service_id' => $service->id,
                    'machine_id' => (int) $payload['machine_id'],
                    'bin_id' => (int) $payload['bin_id'],
                    'product_id' => (int) $payload['product_id'],
                    'transaction_type' => 'fill',
                    'quantity' => $quantity,
                    'price' => $payload['price'],
                    'unit_cost' => $averageUnitCost,
                    'transaction_at' => $transactionAt,
                ]);

                InventoryLedger::create([
                    'account_id' => $service->account_id,
                    'warehouse_id' => (int) $service->warehouse_id,
                    'product_id' => (int) $payload['product_id'],
                    'movement_type' => InventoryLedger::MOVEMENT_TYPE_SERVICE_FILL,
                    'quantity_delta' => -1 * $quantity,
                    'unit_cost' => $averageUnitCost,
                    'total_cost' => -1 * round($quantity * $averageUnitCost, 4),
                    'source_type' => 'service_transaction',
                    'source_id' => $transaction->id,
                    'movement_at' => $transactionAt,
                    'notes' => 'Machine fill from service #'.$service->id,
                ]);
            }
        });
    }

    public function snapshot(int $accountId, int $warehouseId, int $productId): array
    {
        return $this->inventoryCostService->getWarehouseInventorySummary($accountId, $warehouseId, $productId);
    }

    public function inventoryForWarehouse(int $accountId, int $warehouseId, ?string $search = null): Collection
    {
        $query = InventoryLedger::query()
            ->from('tbl_inventory_ledger as l')
            ->join('tbl_products as p', function ($join) use ($accountId) {
                $join->on('p.id', '=', 'l.product_id')
                    ->where('p.account_id', '=', $accountId);
            })
            ->where('l.account_id', $accountId)
            ->where('l.warehouse_id', $warehouseId)
            ->when($search !== null && trim($search) !== '', function ($query) use ($search) {
                $query->where(function ($inventoryQuery) use ($search) {
                    $inventoryQuery
                        ->where('p.sku', 'like', '%'.$search.'%')
                        ->orWhere('p.product_name', 'like', '%'.$search.'%')
                        ->orWhere('p.brand', 'like', '%'.$search.'%')
                        ->orWhere('p.category', 'like', '%'.$search.'%');
                });
            })
            ->groupBy('l.product_id', 'p.sku', 'p.product_name', 'p.size', 'p.package_type')
            ->orderBy('p.product_name')
            ->selectRaw('
                l.product_id,
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
            ');

        return $query->get();
    }

    public function ledgerForWarehouse(int $accountId, int $warehouseId, int $limit = 25): Collection
    {
        return InventoryLedger::query()
            ->where('account_id', $accountId)
            ->where('warehouse_id', $warehouseId)
            ->with('product')
            ->orderByDesc('movement_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }
}
