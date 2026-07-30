<?php

namespace Database\Seeders;

use App\Models\Bin;
use App\Models\InventoryLedger;
use App\Models\Service;
use App\Models\ServiceSale;
use App\Models\Transaction;
use App\Services\CalendarService;
use App\Services\FinalizeServiceSales;
use App\Services\InventoryCostService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class DemoServiceSeeder extends AbstractDemoSeeder
{
    public function run(): void
    {
        $accountId = $this->demoAccount()->id;
        $warehouse = $this->warehouseForAccount($accountId, 'Main Warehouse');
        $technician = $this->userByEmail('tech@example.com');
        $admin = $this->userByEmail('admin@example.com');

        $today = CarbonImmutable::today();
        $mondayThisWeek = $today->startOfWeek(CarbonInterface::MONDAY);
        $wednesdayThisWeek = $mondayThisWeek->addDays(2);
        $fridayThisWeek = $mondayThisWeek->addDays(4);
        $serviceSequence = 0;

        foreach ([8, 7, 6, 4, 2, 1] as $weeksAgo) {
            $serviceSequence++;
            $serviceDate = $mondayThisWeek->subWeeks($weeksAgo);

            $this->seedFinalizedLocationService(
                accountId: $accountId,
                locationName: 'Main Office',
                warehouseId: $warehouse->id,
                technicianUserId: $technician->id,
                createdByUserId: $admin->id,
                closedByUserId: $admin->id,
                serviceDate: $serviceDate,
                serviceSequence: $serviceSequence,
                shouldClose: true,
            );
        }

        foreach ([7, 5, 4, 2, 1] as $weeksAgo) {
            $serviceSequence++;
            $serviceDate = $mondayThisWeek->subWeeks($weeksAgo);

            $this->seedFinalizedLocationService(
                accountId: $accountId,
                locationName: 'Tech Center',
                warehouseId: $warehouse->id,
                technicianUserId: $technician->id,
                createdByUserId: $admin->id,
                closedByUserId: $admin->id,
                serviceDate: $serviceDate,
                serviceSequence: $serviceSequence,
                shouldClose: true,
            );
        }

        foreach ([8, 6, 5, 3, 2] as $weeksAgo) {
            $serviceSequence++;
            $serviceDate = $wednesdayThisWeek->subWeeks($weeksAgo);

            $this->seedFinalizedLocationService(
                accountId: $accountId,
                locationName: 'University Hall',
                warehouseId: $warehouse->id,
                technicianUserId: $technician->id,
                createdByUserId: $admin->id,
                closedByUserId: $admin->id,
                serviceDate: $serviceDate,
                serviceSequence: $serviceSequence,
                shouldClose: true,
            );
        }

        foreach ([7, 5, 3, 1] as $weeksAgo) {
            $serviceSequence++;
            $serviceDate = $fridayThisWeek->subWeeks($weeksAgo);

            $this->seedFinalizedLocationService(
                accountId: $accountId,
                locationName: 'City Gym',
                warehouseId: $warehouse->id,
                technicianUserId: $technician->id,
                createdByUserId: $admin->id,
                closedByUserId: $admin->id,
                serviceDate: $serviceDate,
                serviceSequence: $serviceSequence,
                shouldClose: true,
            );
        }

        foreach ([8, 4, 2] as $weeksAgo) {
            $serviceSequence++;
            $serviceDate = $fridayThisWeek->subWeeks($weeksAgo);

            $this->seedFinalizedLocationService(
                accountId: $accountId,
                locationName: 'Medical Plaza',
                warehouseId: $warehouse->id,
                technicianUserId: $technician->id,
                createdByUserId: $admin->id,
                closedByUserId: $admin->id,
                serviceDate: $serviceDate,
                serviceSequence: $serviceSequence,
                shouldClose: true,
            );
        }

        $serviceSequence++;
        $this->seedFinalizedLocationService(
            accountId: $accountId,
            locationName: 'City Gym',
            warehouseId: $warehouse->id,
            technicianUserId: $technician->id,
            createdByUserId: $admin->id,
            closedByUserId: null,
            serviceDate: $today->subDay(),
            serviceSequence: $serviceSequence,
            shouldClose: false,
        );

        $this->seedOpenLocationService(
            accountId: $accountId,
            locationName: 'Tech Center',
            warehouseId: $warehouse->id,
            technicianUserId: $technician->id,
            createdByUserId: $admin->id,
            serviceDate: $today,
        );

        $this->seedAwaitingLocationService(
            accountId: $accountId,
            locationName: 'Medical Plaza',
            warehouseId: $warehouse->id,
            technicianUserId: $technician->id,
            createdByUserId: $admin->id,
            serviceDate: $today->addDay(),
        );

        $this->seedMaintenanceService(
            accountId: $accountId,
            locationName: 'Main Office',
            technicianUserId: $technician->id,
            createdByUserId: $admin->id,
            closedByUserId: $admin->id,
            serviceDate: $mondayThisWeek->subWeeks(6)->addDay(),
            status: Service::STATUS_SERVICE_CLOSED,
            notes: 'Replaced a worn bill acceptor belt and recalibrated coin mech.',
        );

        $this->seedMaintenanceService(
            accountId: $accountId,
            locationName: 'University Hall',
            technicianUserId: $technician->id,
            createdByUserId: $admin->id,
            closedByUserId: $admin->id,
            serviceDate: $wednesdayThisWeek->subWeeks(4)->addDay(),
            status: Service::STATUS_SERVICE_CLOSED,
            notes: 'Swapped a sticky keypad membrane and tested vend selections.',
        );

        $this->seedMaintenanceService(
            accountId: $accountId,
            locationName: 'City Gym',
            technicianUserId: $technician->id,
            createdByUserId: $admin->id,
            closedByUserId: null,
            serviceDate: $today->subDays(2),
            status: Service::STATUS_SERVICE_OPEN,
            notes: 'Investigating an intermittent refrigeration alarm.',
        );

        $this->seedMaintenanceService(
            accountId: $accountId,
            locationName: 'Main Office',
            technicianUserId: $technician->id,
            createdByUserId: $admin->id,
            closedByUserId: null,
            serviceDate: $today->addDays(4),
            status: Service::STATUS_AWAITING_SERVICE,
            notes: 'Scheduled preventive maintenance and coin chute cleaning.',
        );
    }

    protected function seedFinalizedLocationService(
        int $accountId,
        string $locationName,
        int $warehouseId,
        int $technicianUserId,
        int $createdByUserId,
        ?int $closedByUserId,
        CarbonImmutable $serviceDate,
        int $serviceSequence,
        bool $shouldClose,
    ): void {
        $openedAt = $serviceDate->setTime(8, 30);
        $completedAt = $serviceDate->setTime(11, 15);
        $closedAt = $shouldClose ? $serviceDate->setTime(14, 45) : null;

        $service = $this->upsertService([
            'account_id' => $accountId,
            'location_name' => $locationName,
            'warehouse_id' => $warehouseId,
            'user_id' => $technicianUserId,
            'created_by_user_id' => $createdByUserId,
            'service_date' => $serviceDate->toDateString(),
            'status' => Service::STATUS_SERVICE_OPEN,
            'opened_at' => $openedAt,
            'completed_at' => null,
            'closed_at' => null,
            'closed_by_user_id' => null,
            'amount_collected' => null,
        ]);

        $this->resetServiceArtifacts($service);

        $inventoryCostService = app(InventoryCostService::class);
        $finalizeServiceSales = app(FinalizeServiceSales::class);

        DB::transaction(function () use (
            $service,
            $serviceSequence,
            $openedAt,
            $completedAt,
            $shouldClose,
            $closedAt,
            $closedByUserId,
            $inventoryCostService,
            $finalizeServiceSales
        ) {
            $transactions = $this->serviceTransactionsForSeed($service, $openedAt, $serviceSequence);

            $this->seedCountTransactions($service, $inventoryCostService, $transactions['counts']);
            $this->seedFillTransactions($service, $transactions['fills']);

            $result = $finalizeServiceSales->finalize($service, $completedAt);

            if ($result['errors'] !== []) {
                throw new \RuntimeException('Demo service seeding failed: '.implode(' ', $result['errors']));
            }

            $service->update([
                'status' => $shouldClose ? Service::STATUS_SERVICE_CLOSED : Service::STATUS_SERVICE_COMPLETED,
                'completed_at' => $completedAt,
                'closed_at' => $closedAt,
                'closed_by_user_id' => $closedByUserId,
                'amount_collected' => $shouldClose
                    ? round($result['sales_total_cents'] / 100, 2)
                    : null,
            ]);
        });

        $this->syncServiceCalendarEvent($service, $createdByUserId);
    }

    protected function seedOpenLocationService(
        int $accountId,
        string $locationName,
        int $warehouseId,
        int $technicianUserId,
        int $createdByUserId,
        CarbonImmutable $serviceDate,
    ): void {
        $service = $this->upsertService([
            'account_id' => $accountId,
            'location_name' => $locationName,
            'warehouse_id' => $warehouseId,
            'user_id' => $technicianUserId,
            'created_by_user_id' => $createdByUserId,
            'service_date' => $serviceDate->toDateString(),
            'status' => Service::STATUS_SERVICE_OPEN,
            'opened_at' => $serviceDate->setTime(9, 0),
            'completed_at' => null,
            'closed_at' => null,
            'closed_by_user_id' => null,
            'amount_collected' => null,
        ]);

        $this->resetServiceArtifacts($service);

        DB::transaction(function () use ($service, $serviceDate) {
            $inventoryCostService = app(InventoryCostService::class);

            $this->seedCountTransactions($service, $inventoryCostService, [
                [
                    'serial_number' => 'TC-COMBO-001',
                    'bin_code' => 'A1',
                    'quantity' => 11,
                    'spoilage' => 0,
                    'transaction_at' => $serviceDate->setTime(9, 18),
                ],
                [
                    'serial_number' => 'TC-COMBO-001',
                    'bin_code' => 'A3',
                    'quantity' => 9,
                    'spoilage' => 1,
                    'transaction_at' => $serviceDate->setTime(9, 21),
                ],
                [
                    'serial_number' => 'TC-COMBO-001',
                    'bin_code' => 'B1',
                    'quantity' => 12,
                    'spoilage' => 0,
                    'transaction_at' => $serviceDate->setTime(9, 25),
                ],
                [
                    'serial_number' => 'TC-COMBO-001',
                    'bin_code' => 'B4',
                    'quantity' => 8,
                    'spoilage' => 0,
                    'transaction_at' => $serviceDate->setTime(9, 29),
                ],
            ]);
        });

        $this->syncServiceCalendarEvent($service, $createdByUserId);
    }

    protected function seedAwaitingLocationService(
        int $accountId,
        string $locationName,
        int $warehouseId,
        int $technicianUserId,
        int $createdByUserId,
        CarbonImmutable $serviceDate,
    ): void {
        $service = $this->upsertService([
            'account_id' => $accountId,
            'location_name' => $locationName,
            'warehouse_id' => $warehouseId,
            'user_id' => $technicianUserId,
            'created_by_user_id' => $createdByUserId,
            'service_date' => $serviceDate->toDateString(),
            'status' => Service::STATUS_AWAITING_SERVICE,
            'opened_at' => null,
            'completed_at' => null,
            'closed_at' => null,
            'closed_by_user_id' => null,
            'amount_collected' => null,
            'notes' => 'Restock and collection scheduled for the Friday route.',
        ]);

        $this->resetServiceArtifacts($service);
        $this->syncServiceCalendarEvent($service, $createdByUserId, CalendarService::REMINDER_OPTION_1_DAY);
    }

    protected function seedMaintenanceService(
        int $accountId,
        string $locationName,
        int $technicianUserId,
        int $createdByUserId,
        ?int $closedByUserId,
        CarbonImmutable $serviceDate,
        string $status,
        string $notes,
    ): void {
        $openedAt = null;
        $closedAt = null;

        if (strcasecmp($status, Service::STATUS_SERVICE_OPEN) === 0) {
            $openedAt = $serviceDate->setTime(10, 15);
        }

        if (strcasecmp($status, Service::STATUS_SERVICE_CLOSED) === 0) {
            $openedAt = $serviceDate->setTime(9, 10);
            $closedAt = $serviceDate->setTime(11, 40);
        }

        $service = $this->upsertService([
            'account_id' => $accountId,
            'location_name' => $locationName,
            'warehouse_id' => null,
            'user_id' => $technicianUserId,
            'created_by_user_id' => $createdByUserId,
            'service_date' => $serviceDate->toDateString(),
            'service_type' => Service::TYPE_MAINTENANCE_SERVICE,
            'status' => $status,
            'opened_at' => $openedAt,
            'completed_at' => null,
            'closed_at' => $closedAt,
            'closed_by_user_id' => $closedByUserId,
            'amount_collected' => null,
            'notes' => $notes,
        ]);

        $this->resetServiceArtifacts($service);

        $reminderOption = strcasecmp($status, Service::STATUS_AWAITING_SERVICE) === 0
            ? CalendarService::REMINDER_OPTION_1_DAY
            : null;

        $this->syncServiceCalendarEvent($service, $createdByUserId, $reminderOption);
    }

    protected function serviceTransactionsForSeed(Service $service, CarbonImmutable $openedAt, int $serviceSequence): array
    {
        $bins = Bin::query()
            ->where('account_id', $service->account_id)
            ->whereHas('machine', fn ($query) => $query->where('location_id', $service->location_id))
            ->with(['machine', 'product'])
            ->orderBy('machine_id')
            ->orderBy('bin_code')
            ->get();

        $counts = [];
        $fills = [];

        foreach ($bins as $index => $bin) {
            $openingInventory = $this->latestInventorySnapshot($service, $bin, $openedAt);
            $capacity = max(1, (int) $bin->capacity);
            $spoilage = $this->spoilageForBin($bin, $serviceSequence, $index, $openingInventory !== null);
            $targetClosing = max(1, $capacity - (($serviceSequence + $index) % 3));

            if ($openingInventory === null) {
                $baselineGap = min($targetClosing - 1, $this->baselineGapForBin($bin, $serviceSequence, $index));
                $countQuantity = max(1, $targetClosing - $baselineGap);
            } else {
                $openingQuantity = (int) $openingInventory->quantity;
                $unitsSold = min(
                    max(1, $openingQuantity - $spoilage),
                    $this->unitsSoldTargetForBin($bin, $serviceSequence, $index, $service->location?->location_name)
                );

                if ($openingQuantity - $unitsSold - $spoilage < 0) {
                    $unitsSold = max(0, $openingQuantity - $spoilage);
                }

                $countQuantity = max(0, $openingQuantity - $unitsSold - $spoilage);
            }

            $fillQuantity = max(0, $targetClosing - $countQuantity);

            $counts[] = [
                'serial_number' => (string) $bin->machine?->serial_number,
                'bin_code' => $bin->bin_code,
                'quantity' => $countQuantity,
                'spoilage' => $spoilage,
                'transaction_at' => $openedAt->addMinutes(25 + ($index * 3)),
            ];

            if ($fillQuantity > 0) {
                $fills[] = [
                    'serial_number' => (string) $bin->machine?->serial_number,
                    'bin_code' => $bin->bin_code,
                    'quantity' => $fillQuantity,
                    'transaction_at' => $openedAt->addMinutes(80 + ($index * 3)),
                ];
            }
        }

        return [
            'counts' => $counts,
            'fills' => $fills,
        ];
    }

    protected function latestInventorySnapshot(Service $service, Bin $bin, CarbonImmutable $openedAt): ?Transaction
    {
        return Transaction::query()
            ->where('account_id', $service->account_id)
            ->where('bin_id', $bin->id)
            ->where('product_id', $bin->product_id)
            ->where('transaction_type', Transaction::TYPE_CURRENT_INVENTORY)
            ->where('transaction_at', '<', $openedAt)
            ->orderByDesc('transaction_at')
            ->orderByDesc('id')
            ->first();
    }

    protected function unitsSoldTargetForBin(Bin $bin, int $serviceSequence, int $index, ?string $locationName): int
    {
        $category = strtolower((string) $bin->product?->category);
        $base = match ($category) {
            'soda' => 3,
            'water' => 2,
            'sports drink' => 2,
            'chips', 'cookies', 'candy', 'candy bar' => 2,
            default => 1,
        };

        $locationAdjustment = match ($locationName) {
            'University Hall', 'Tech Center' => 1,
            'City Gym' => $category === 'sports drink' ? 1 : 0,
            default => 0,
        };

        return $base + $locationAdjustment + (($serviceSequence + $index) % 2);
    }

    protected function spoilageForBin(Bin $bin, int $serviceSequence, int $index, bool $hasOpeningInventory): int
    {
        if (! $hasOpeningInventory) {
            return 0;
        }

        $category = strtolower((string) $bin->product?->category);

        if (! in_array($category, ['chips', 'cookies', 'candy', 'candy bar'], true)) {
            return 0;
        }

        return ($serviceSequence + $index) % 9 === 0 ? 1 : 0;
    }

    protected function baselineGapForBin(Bin $bin, int $serviceSequence, int $index): int
    {
        $category = strtolower((string) $bin->product?->category);
        $base = match ($category) {
            'soda' => 3,
            'water' => 2,
            'sports drink' => 2,
            default => 2,
        };

        return $base + (($serviceSequence + $index) % 2);
    }

    protected function resetServiceArtifacts(Service $service): void
    {
        $transactionIds = Transaction::query()
            ->where('account_id', $service->account_id)
            ->where('service_id', $service->id)
            ->pluck('id');

        if ($transactionIds->isNotEmpty()) {
            InventoryLedger::query()
                ->where('account_id', $service->account_id)
                ->where('source_type', 'service_transaction')
                ->whereIn('source_id', $transactionIds)
                ->delete();
        }

        ServiceSale::query()
            ->where('account_id', $service->account_id)
            ->where('service_id', $service->id)
            ->delete();

        Transaction::query()
            ->where('account_id', $service->account_id)
            ->where('service_id', $service->id)
            ->delete();
    }

    protected function syncServiceCalendarEvent(
        Service $service,
        int $createdByUserId,
        ?string $reminderOption = null,
    ): void {
        $calendarService = app(CalendarService::class);
        $event = $calendarService->createServiceEvent($service->fresh(['location.primaryRouteLocation.route']), $createdByUserId);

        $calendarService->syncReminder(
            $event,
            $reminderOption ?? CalendarService::REMINDER_OPTION_NONE,
            null,
            $reminderOption !== null ? 'Upcoming demo service at '.$service->location?->location_name.'.' : null,
        );
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
                'created_by_user_id' => $definition['created_by_user_id'] ?? null,
                'closed_by_user_id' => $definition['closed_by_user_id'],
                'service_type' => $definition['service_type'] ?? Service::TYPE_LOCATION_SERVICE,
                'notes' => $definition['notes'] ?? null,
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
                    'transaction_type' => Transaction::TYPE_COUNT,
                    'transaction_at' => $definition['transaction_at'],
                ],
                [
                    'account_id' => $service->account_id,
                    'machine_id' => $bin->machine_id,
                    'product_id' => $bin->product_id,
                    'quantity' => (int) $definition['quantity'],
                    'spoilage' => (int) ($definition['spoilage'] ?? 0),
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
                    'transaction_type' => Transaction::TYPE_FILL,
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
