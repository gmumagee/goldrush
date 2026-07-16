<?php

namespace Database\Seeders;

use App\Models\InventoryLedger;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use Illuminate\Support\Facades\DB;

class DemoPurchaseSeeder extends DemoSeeder
{
    public function run(): void
    {
        $accountId = $this->demoAccount()->id;
        $warehouse = $this->warehouseForAccount($accountId, 'Main Warehouse');

        foreach ($this->purchases() as $purchaseDefinition) {
            DB::transaction(function () use ($accountId, $warehouse, $purchaseDefinition) {
                $vendor = $this->vendorForAccount($accountId, $purchaseDefinition['vendor_name']);

                $purchase = Purchase::query()->updateOrCreate(
                    [
                        'account_id' => $accountId,
                        'invoice_number' => $purchaseDefinition['invoice_number'],
                    ],
                    [
                        'vendor_id' => $vendor->id,
                        'warehouse_id' => $warehouse->id,
                        'purchase_date' => $purchaseDefinition['purchase_date'],
                        'status' => Purchase::STATUS_POSTED,
                        'notes' => $purchaseDefinition['notes'],
                    ],
                );

                foreach ($purchaseDefinition['items'] as $itemDefinition) {
                    $product = $this->productForAccount($accountId, $itemDefinition['sku']);
                    $quantity = (int) $itemDefinition['quantity'];
                    $lineTotal = round((float) $itemDefinition['line_total'], 2);
                    $unitCost = $quantity > 0 ? round($lineTotal / $quantity, 4) : 0.0;

                    $purchaseItem = PurchaseItem::query()->updateOrCreate(
                        [
                            'purchase_id' => $purchase->id,
                            'product_id' => $product->id,
                        ],
                        [
                            'account_id' => $accountId,
                            'quantity' => $quantity,
                            'line_total' => $lineTotal,
                            'unit_cost' => $unitCost,
                        ],
                    );

                    InventoryLedger::query()->updateOrCreate(
                        [
                            'source_type' => 'purchase_item',
                            'source_id' => $purchaseItem->id,
                            'movement_type' => InventoryLedger::MOVEMENT_TYPE_PURCHASE,
                        ],
                        [
                            'account_id' => $accountId,
                            'warehouse_id' => $warehouse->id,
                            'product_id' => $product->id,
                            'quantity_delta' => $quantity,
                            'unit_cost' => $unitCost,
                            'total_cost' => round($lineTotal, 4),
                            'movement_at' => $purchase->purchase_date,
                            'notes' => 'Posted purchase '.$purchase->invoice_number,
                        ],
                    );
                }
            });
        }
    }

    protected function purchases(): array
    {
        return [
            [
                'vendor_name' => 'Costco Business Center',
                'invoice_number' => 'DEMO-1001',
                'purchase_date' => today()->subDays(14)->toDateString(),
                'notes' => 'Demo beverage replenishment purchase.',
                'items' => [
                    ['sku' => 'COKE-12-CAN', 'quantity' => 120, 'line_total' => 60.00],
                    ['sku' => 'DIETCOKE-12-CAN', 'quantity' => 120, 'line_total' => 60.00],
                    ['sku' => 'PEPSI-12-CAN', 'quantity' => 120, 'line_total' => 58.80],
                    ['sku' => 'AQUAFINA-169-BTL', 'quantity' => 96, 'line_total' => 48.00],
                    ['sku' => 'GATORADE-FP-20-BTL', 'quantity' => 48, 'line_total' => 60.00],
                ],
            ],
            [
                'vendor_name' => "Sam's Club",
                'invoice_number' => 'DEMO-1002',
                'purchase_date' => today()->subDays(10)->toDateString(),
                'notes' => 'Demo snack replenishment purchase.',
                'items' => [
                    ['sku' => 'DORITOS-NACHO-175-BAG', 'quantity' => 80, 'line_total' => 48.00],
                    ['sku' => 'LAYS-CLASSIC-15-BAG', 'quantity' => 80, 'line_total' => 40.00],
                    ['sku' => 'SNICKERS-186-BAR', 'quantity' => 72, 'line_total' => 50.40],
                    ['sku' => 'MMS-PEANUT-174-BAG', 'quantity' => 72, 'line_total' => 50.40],
                    ['sku' => 'OREO-24-PACK', 'quantity' => 60, 'line_total' => 39.00],
                ],
            ],
        ];
    }
}
