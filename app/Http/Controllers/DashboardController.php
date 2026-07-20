<?php

namespace App\Http\Controllers;

use App\Models\CalendarEvent;
use App\Models\Warehouse;
use App\Services\DashboardSalesChartService;
use App\Services\WarehouseInventoryService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        protected WarehouseInventoryService $warehouseInventoryService,
        protected DashboardSalesChartService $dashboardSalesChartService,
    )
    {
    }

    public function __invoke(Request $request): View
    {
        $accountId = $this->currentAccountId($request);
        $selectedDate = $request->filled('date')
            ? Carbon::parse($request->string('date'))
            : now();

        $weekStart = $selectedDate->copy()
            ->startOfWeek(Carbon::SUNDAY)
            ->startOfDay();

        $weekEnd = $selectedDate->copy()
            ->endOfWeek(Carbon::SATURDAY)
            ->endOfDay();

        $weekDays = collect();

        for ($date = $weekStart->copy(); $date->lte($weekEnd); $date->addDay()) {
            $weekDays->push($date->copy());
        }

        $events = CalendarEvent::query()
            ->forAccount($accountId)
            ->with('assignedUser')
            ->where('status', CalendarEvent::STATUS_SCHEDULED)
            ->whereBetween('start_at', [$weekStart, $weekEnd])
            ->orderBy('start_at')
            ->orderBy('id')
            ->get();

        $eventsByDate = $events->groupBy(fn (CalendarEvent $event) => $event->start_at?->toDateString());

        // Resolve the current account's Main Warehouse explicitly so the card
        // never mixes inventory from another warehouse or account.
        $mainWarehouseCandidates = Warehouse::query()
            ->where('account_id', $accountId)
            ->where('warehouse_name', 'Main Warehouse')
            ->orderBy('id')
            ->limit(2)
            ->get();

        $mainWarehouse = null;
        $mainWarehouseLowInventoryProducts = collect();
        $mainWarehouseInventoryState = 'missing';
        $salesChart = $this->dashboardSalesChartService->buildForAccount($accountId);

        if ($mainWarehouseCandidates->count() > 1) {
            $mainWarehouseInventoryState = 'duplicate';
        } elseif ($mainWarehouseCandidates->count() === 1) {
            $mainWarehouse = $mainWarehouseCandidates->first();
            $mainWarehouseLowInventoryProducts = $this->warehouseInventoryService
                ->lowInventoryForWarehouse($accountId, $mainWarehouse->id, 10);
            $mainWarehouseInventoryState = $mainWarehouseLowInventoryProducts->isEmpty()
                ? 'no_inventory_data'
                : 'ready';
        }

        return view('dashboard', [
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'weekDays' => $weekDays,
            'eventsByDate' => $eventsByDate,
            'mainWarehouse' => $mainWarehouse,
            'mainWarehouseLowInventoryProducts' => $mainWarehouseLowInventoryProducts,
            'mainWarehouseInventoryState' => $mainWarehouseInventoryState,
            'salesChart' => $salesChart,
        ]);
    }
}
