<?php

namespace Database\Seeders;

use App\Models\Bin;
use App\Models\InventoryLedger;
use App\Models\Service;
use App\Models\Transaction;
use App\Services\InventoryCostService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class DemoServiceSeeder extends DemoSeeder
{
    public function run(): void
    {
        $accountId = $this->demoAccount()->id;
        $warehouse = $this->warehouseForAccount($accountId, 'Main Warehouse');
        $technician = $this->userByEmail('tech@example.com');
        $admin = $this->userByEmail('admin@example.com');

        $today = CarbonImmutable::today();

        $awaitingService = $this->upsertService([
            'account_id' => $accountId,
            'location_name' => 'Medical Plaza',
            'warehouse_id' => $warehouse->id,
            'user_id' => $technician->id,
            'service_date' => $today->addDay()->toDateString(),
            'status' => Service::STATUS_AWAITING_SERVICE,
            'opened_at' => null,
            'completed_at' => null,
            'closed_at' => null,
            'closed_by_user_id' => null,
            'amount_collected' => null,
        ]);

        $openService = $this->upsertService([
            'account_id' => $accountId,
            'location_name' => 'Tech Center',
            'warehouse_id' => $warehouse->id,
            'user_id' => $technician->id,
            'service_date' => $today->toDateString(),
            'status' => Service::STATUS_SERVICE_OPEN,
            'opened_at' => $today->setTime(9, 0),
            'completed_at' => null,
            'closed_at' => null,
            'closed_by_user_id' => null,
            'amount_collected' => null,
        ]);

        $completedService = $this->upsertService([
            'account_id' => $accountId,
            'location_name' => 'City Gym',
            'warehouse_id' => $warehouse->id,
            'user_id' => $technician->id,
            'service_date' => $today->subDay()->toDateString(),
            'status' => Service::STATUS_SERVICE_COMPLETED,
            'opened_at' => $today->subDay()->setTime(9, 0),
            'completed_at' => $today->subDay()->setTime(11, 30),
            'closed_at' => null,
            'closed_by_user_id' => null,
            'amount_collected' => null,
        ]);

        $closedService = $this->upsertService([
            'account_id' => $accountId,
            'location_name' => 'Main Office',
            'warehouse_id' => $warehouse->id,
            'user_id' => $technician->id,
            'service_date' => $today->subDays(2)->toDateString(),
            'status' => Service::STATUS_SERVICE_CLOSED,
            'opened_at' => $today->subDays(2)->setTime(8, 30),
            'completed_at' => $today->subDays(2)->setTime(10, 45),
            'closed_at' => $today->subDays(2)->setTime(15, 0),
            'closed_by_user_id' => $admin->id,
            'amount_collected' => 184.25,
        ]);

        $inventoryCostService = app(InventoryCostService::class);

        DB::transaction(function () use ($accountId, $openService, $inventoryCostService, $today) {
            $this->seedCountTransactions($openService, $inventoryCostService, [
                [
                    'serial_number' => 'TC-COMBO-001',
                    'bin_code' => 'A1',
                    'quantity' => 7,
                    'transaction_at' => $today->setTime(9, 20),
                ],
                [
                    'serial_number' => 'TC-COMBO-001',
                    'bin_code' => 'A3',
                    'quantity' => 5,
                    'transaction_at' => $today->setTime(9, 22),
                ],
                [
                    'serial_number' => 'TC-COMBO-001',
                    'bin_code' => 'B1',
                    'quantity' => 9,
                    'transaction_at' => $today->setTime(9, 25),
                ],
                [
                    'serial_number' => 'TC-COMBO-001',
                    'bin_code' => 'B4',
                    'quantity' => 6,
                    'transaction_at' => $today->setTime(9, 28),
                ],
            ]);
        });

        DB::transaction(function () use ($accountId, $closedService, $inventoryCostService, $today) {
            $this->seedCountTransactions($closedService, $inventoryCostService, [
                [
                    'serial_number' => 'MO-SNACK-001',
                    'bin_code' => 'A1',
                    'quantity' => 4,
                    'transaction_at' => $today->subDays(2)->setTime(9, 5),
                ],
                [
                    'serial_number' => 'MO-SNACK-001',
                    'bin_code' => 'A2',
                    'quantity' => 6,
                    'transaction_at' => $today->subDays(2)->setTime(9, 7),
                ],
                [
                    'serial_number' => 'MO-SNACK-001',
                    'bin_code' => 'A3',
                    'quantity' => 3,
                    'transaction_at' => $today->subDays(2)->setTime(9, 10),
                ],
                [
                    'serial_number' => 'MO-SODA-001',
                    'bin_code' => 'B1',
                    'quantity' => 8,
                    'transaction_at' => $today->subDays(2)->setTime(9, 40),
                ],
                [
                    'serial_number' => 'MO-SODA-001',
                    'bin_code' => 'B4',
                    'quantity' => 5,
                    'transaction_at' => $today->subDays(2)->setTime(9, 45),
                ],
            ]);

            $this->seedFillTransactions($closedService, [
                [
                    'serial_number' => 'MO-SNACK-001',
                    'bin_code' => 'A1',
                    'quantity' => 10,
                    'transaction_at' => $today->subDays(2)->setTime(10, 0),
                ],
                [
                    'serial_number' => 'MO-SNACK-001',
                    'bin_code' => 'A2',
                    'quantity' => 8,
                    'transaction_at' => $today->subDays(2)->setTime(10, 5),
                ],
                [
                    'serial_number' => 'MO-SNACK-001',
                    'bin_code' => 'A3',
                    'quantity' => 10,
                    'transaction_at' => $today->subDays(2)->setTime(10, 10),
                ],
                [
                    'serial_number' => 'MO-SODA-001',
                    'bin_code' => 'B1',
                    'quantity' => 12,
                    'transaction_at' => $today->subDays(2)->setTime(10, 20),
                ],
                [
                    'serial_number' => 'MO-SODA-001',
                    'bin_code' => 'B4',
                    'quantity' => 15,
                    'transaction_at' => $today->subDays(2)->setTime(10, 25),
                ],
            ]);
        });

        unset($awaitingService, $completedService);
    }

    protected function upsertService(array $definition): Service
    {
        $location = $this->locationForAccount($definition['account_id'], $definition['location_name']);

        return Service::query()->updateOrCreate(
            [
                'account_id' => $definition['account_id'],
                'location_id' => $location->id,
                'service_date' => $definition['service_date'],
            ],
            [
                'warehouse_id' => $definition['warehouse_id'],
                'user_id' => $definition['user_id'],
                'closed_by_user_id' => $definition['closed_by_user_id'],
                'service_type' => Service::TYPE_LOCATION_SERVICE,
                'opened_at' => $definition['opened_at'],
                'completed_at' => $definition['completed_at'],
                'closed_at' => $definition['closed_at'],
                'amount_collected' => $definition['amount_collected'],
                'status' => $definition['status'],
            ],
        );
    }

    protected function seedCountTransactions(Service $service, InventoryCostService $inventoryCostService, array $transactions): void
    {
        foreach ($transactions as $definition) {
            $bin = $this->binForService($service, $definition['serial_number'], $definition['bin_code']);

            Transaction::query()->updateOrCreate(
                [
                    'service_id' => $service->id,
                    'bin_id' => $bin->id,
                    'transaction_type' => 'count',
                    'transaction_at' => $definition['transaction_at'],
                ],
                [
                    'account_id' => $service->account_id,
                    'machine_id' => $bin->machine_id,
                    'product_id' => $bin->product_id,
                    'quantity' => (int) $definition['quantity'],
                    'price' => $bin->price,
                    'unit_cost' => $inventoryCostService->getUnitCostForCount(
                        $service->account_id,
                        $service->warehouse_id ? (int) $service->warehouse_id : null,
                        $bin->id,
                        $bin->product_id ? (int) $bin->product_id : null,
                    ),
                ],
            );
        }
    }

    protected function seedFillTransactions(Service $service, array $transactions): void
    {
        foreach ($transactions as $definition) {
            $bin = $this->binForService($service, $definition['serial_number'], $definition['bin_code']);
            $unitCost = app(InventoryCostService::class)->getCurrentAverageUnitCost(
                $service->account_id,
                (int) $service->warehouse_id,
                (int) $bin->product_id,
            );
            $quantity = (int) $definition['quantity'];

            $transaction = Transaction::query()->updateOrCreate(
                [
                    'service_id' => $service->id,
                    'bin_id' => $bin->id,
                    'transaction_type' => 'fill',
                    'transaction_at' => $definition['transaction_at'],
                ],
                [
                    'account_id' => $service->account_id,
                    'machine_id' => $bin->machine_id,
                    'product_id' => $bin->product_id,
                    'quantity' => $quantity,
                    'price' => $bin->price,
                    'unit_cost' => $unitCost,
                ],
            );

            InventoryLedger::query()->updateOrCreate(
                [
                    'source_type' => 'service_transaction',
                    'source_id' => $transaction->id,
                    'movement_type' => InventoryLedger::MOVEMENT_TYPE_SERVICE_FILL,
                ],
                [
                    'account_id' => $service->account_id,
                    'warehouse_id' => (int) $service->warehouse_id,
                    'product_id' => (int) $bin->product_id,
                    'quantity_delta' => -1 * $quantity,
                    'unit_cost' => $unitCost,
                    'total_cost' => -1 * round($quantity * $unitCost, 4),
                    'movement_at' => $definition['transaction_at'],
                    'notes' => 'Machine fill from service #'.$service->id,
                ],
            );
        }
    }

    protected function binForService(Service $service, string $serialNumber, string $binCode): Bin
    {
        $machine = $this->machineForAccount($service->account_id, $serialNumber);

        return Bin::query()
            ->where('account_id', $service->account_id)
            ->where('machine_id', $machine->id)
            ->where('bin_code', $binCode)
            ->firstOrFail();
    }
}
