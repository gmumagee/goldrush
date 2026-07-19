<x-app-layout title="Service Details">
        <div class="px-4 py-8 sm:px-6 lg:px-8">
            <div class="mx-auto w-full max-w-7xl space-y-6">
                @php
                    // Derive the display state once so every service panel reflects the same workflow rules.
                    $statusClasses = match (strtolower(trim((string) $service->status))) {
                        strtolower(\App\Models\Service::STATUS_AWAITING_SERVICE) => 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300',
                        strtolower(\App\Models\Service::STATUS_SERVICE_OPEN) => 'bg-blue-100 text-blue-800 dark:bg-blue-500/15 dark:text-blue-300',
                    strtolower(\App\Models\Service::STATUS_SERVICE_COMPLETED) => 'bg-violet-100 text-violet-800 dark:bg-violet-500/15 dark:text-violet-300',
                    strtolower(\App\Models\Service::STATUS_SERVICE_CLOSED) => 'bg-green-100 text-green-800 dark:bg-green-500/15 dark:text-green-300',
                    default => 'bg-gray-100 text-gray-700 dark:bg-gray-700/60 dark:text-gray-200',
                };
                $statusLabel = $serviceStatusLabels[strtolower(trim((string) $service->status))] ?? ($service->status ?: 'Unknown');
                $serviceTypeLabel = $serviceTypeLabels[strtolower(trim((string) $service->service_type))] ?? ($service->service_type ?: 'Unknown');
                $serviceSalesTotal = isset($service->sales_total) ? (string) $service->sales_total : null;
                $calculatedSalesCount = (int) ($service->calculated_sales_count ?? $service->sales->filter(fn ($sale) => $sale->isCalculated())->count());
                $baselineSalesCount = (int) ($service->baseline_sales_count ?? $service->sales->filter(fn ($sale) => $sale->isBaseline())->count());
                $reconciliationStatus = match (true) {
                    $calculatedSalesCount > 0 && $baselineSalesCount === 0 => 'complete',
                    $calculatedSalesCount > 0 && $baselineSalesCount > 0 => 'partial',
                    $calculatedSalesCount === 0 && $baselineSalesCount > 0 => 'baseline_only',
                    default => 'none',
                };
                $hasBaselineRows = $baselineSalesCount > 0;
                $serviceDifference = null;

                if ($reconciliationStatus === 'complete' && $serviceSalesTotal !== null && $service->amount_collected !== null) {
                    $serviceDifference = \App\Support\Money::fromCents(
                        \App\Support\Money::toCents($service->amount_collected)
                        - \App\Support\Money::toCents($serviceSalesTotal)
                    );
                }

                $serviceSalesDisplay = match ($reconciliationStatus) {
                    'complete' => \App\Support\Money::format($serviceSalesTotal),
                    'partial' => trim(\App\Support\Money::format($serviceSalesTotal).' Partial'),
                    'baseline_only' => 'Not available — baseline service',
                    default => '—',
                };
            @endphp

            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 md:text-3xl">Service #{{ $service->id }}</h1>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        {{ $service->location?->location_name ?? 'No location' }}{{ $service->location?->city ? ', '.$service->location->city : '' }} - {{ $service->location?->route?->route_name ?? 'No route' }}
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <a href="{{ route('services.index') }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                        Back to Services
                    </a>

                    @if ($serviceCalendarEvent)
                        <a href="{{ route('calendar-events.show', $serviceCalendarEvent) }}" class="inline-flex items-center rounded-xl border border-violet-300 px-4 py-2 text-sm font-medium text-violet-700 transition hover:bg-violet-50 dark:border-violet-500/40 dark:text-violet-300 dark:hover:bg-violet-500/10">
                            View Calendar Event
                        </a>
                    @endif

                    @if ($service->isAwaitingService() && $service->isLocationService())
                        <form method="POST" action="{{ route('services.open', $service->id) }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-blue-500">
                                Open Service
                            </button>
                        </form>
                    @endif

                    @if ($service->isAwaitingService() && $service->isMaintenanceService())
                        <form method="POST" action="{{ route('services.maintenance.open', $service->id) }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center rounded-xl bg-yellow-500 px-4 py-2.5 text-sm font-medium text-yellow-950 transition hover:bg-yellow-400">
                                Open Maintenance Service
                            </button>
                        </form>
                    @endif

                    @if ($service->isServiceOpen() && $service->isLocationService())
                        <form method="POST" action="{{ route('services.complete', $service->id) }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center rounded-xl bg-green-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-green-500">
                                Complete Service
                            </button>
                        </form>
                    @endif

                    @if ($service->isServiceCompleted() && $service->amount_collected === null && $service->isLocationService())
                        <a href="{{ route('services.amount-collected.edit', $service->id) }}" class="inline-flex items-center rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-violet-500">
                            Enter Amount Collected
                        </a>
                    @endif
                </div>
            </div>

            @if (session('status'))
                <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-900/60 dark:bg-green-500/10 dark:text-green-300">
                    {{ session('status') }}
                </div>
            @endif

            @if ($hasBaselineRows && ($service->isServiceCompleted() || $service->isServiceClosed()))
                <div class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-700 dark:border-blue-500/30 dark:bg-blue-500/10 dark:text-blue-200">
                    Some bins were initialized during this service because no previous inventory snapshot existed. Sales for those bins will be calculated beginning with their next service.
                </div>
            @endif

            <x-validation-errors />

            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Service Summary</h2>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700/60">
                        <thead class="bg-gray-50 dark:bg-gray-800/80">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Service Type</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Status</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Source Warehouse</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Service Date</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Opened At</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Completed At</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Closed At</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Closed By</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Sales</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Amount Collected</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Difference</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Assigned User</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700/60">
                            <tr class="bg-white dark:bg-gray-800">
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $serviceTypeLabel }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $statusClasses }}">
                                        {{ $statusLabel }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $service->isLocationService() ? ($service->warehouse?->warehouse_name ?? '—') : 'N/A' }}</td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ \App\Support\AppDateTime::displayDate($service->service_date) }}</td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ \App\Support\AppDateTime::displayTime($service->opened_at) }}</td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ \App\Support\AppDateTime::displayTime($service->completed_at) }}</td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ \App\Support\AppDateTime::displayTime($service->closed_at) }}</td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $service->closedBy?->name ?? 'Not closed yet' }}</td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                    @if ($service->isMaintenanceService())
                                        N/A
                                    @elseif ($service->isServiceCompleted() || $service->isServiceClosed())
                                        {{ $serviceSalesDisplay }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                    @if ($service->isMaintenanceService())
                                        N/A
                                    @elseif ($service->amount_collected !== null)
                                        {{ \App\Support\Money::format($service->amount_collected) }}
                                    @elseif ($service->isServiceCompleted())
                                        Pending
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                    @if ($service->isMaintenanceService())
                                        N/A
                                    @elseif ($serviceDifference !== null)
                                        {{ \App\Support\Money::format($serviceDifference) }}
                                    @elseif (in_array($reconciliationStatus, ['partial', 'baseline_only'], true) && ($service->isServiceCompleted() || $service->isServiceClosed()))
                                        Unavailable
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $service->user?->name ?? '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            @if ($service->isLocationService() && ($service->isServiceCompleted() || $service->isServiceClosed()))
            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Sales Breakdown</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Finalized bin-level sales facts recorded when this service was completed.</p>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700/60">
                        <thead class="bg-gray-50 dark:bg-gray-800/80">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Machine</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Bin</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Product</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Status</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-500 dark:text-gray-400">Opening</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-500 dark:text-gray-400">Additions</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-500 dark:text-gray-400">Removals</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-500 dark:text-gray-400">Count</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-500 dark:text-gray-400">Units Sold</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-500 dark:text-gray-400">Unit Price</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-500 dark:text-gray-400">Sales</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700/60">
                            @forelse ($service->sales as $sale)
                                @php
                                    // Prefer stable machine identifiers so stored sales rows remain readable later.
                                    $machineLabel = $sale->machine?->serial_number
                                        ?: $sale->machine?->model
                                        ?: $sale->machine?->type
                                        ?: '—';
                                @endphp
                                <tr class="bg-white dark:bg-gray-800">
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $machineLabel }}</td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $sale->bin?->bin_code ?? '—' }}</td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $sale->product?->product_name ?? '—' }}</td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                        @if ($sale->isBaseline())
                                            <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700 dark:bg-gray-700/60 dark:text-gray-200">Baseline</span>
                                        @else
                                            <span class="inline-flex rounded-full bg-green-100 px-2.5 py-1 text-xs font-medium text-green-700 dark:bg-green-500/15 dark:text-green-300">Calculated</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-300">{{ $sale->isBaseline() ? '—' : $sale->opening_quantity }}</td>
                                    <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-300">{{ $sale->inventory_additions }}</td>
                                    <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-300">{{ $sale->non_sale_removals }}</td>
                                    <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-300">{{ $sale->counted_quantity }}</td>
                                    <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-300">{{ $sale->isBaseline() ? '—' : $sale->units_sold }}</td>
                                    <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-300">{{ \App\Support\Money::format($sale->unit_price) }}</td>
                                    <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-300">{{ $sale->isBaseline() ? '—' : \App\Support\Money::format($sale->sales_amount) }}</td>
                                </tr>
                            @empty
                                <tr class="bg-white dark:bg-gray-800">
                                    <td colspan="11" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">
                                        No finalized sales lines are stored for this service yet.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
            @endif

            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">{{ $service->isMaintenanceService() ? 'Maintenance Notes' : 'Service Notes' }}</h2>
                    </div>
                </div>
                <div class="panel-body">
                    @if ($service->isMaintenanceService() && $service->isServiceOpen())
                        <form method="POST" action="{{ route('services.maintenance.close', $service) }}" class="space-y-4">
                            @csrf
                            @method('PUT')

                            <div>
                                <x-label for="notes" value="Maintenance Notes" />
                                <textarea id="notes" name="notes" rows="5" class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100">{{ old('notes', $service->notes) }}</textarea>
                            </div>

                            <button type="submit" class="inline-flex items-center rounded-xl bg-green-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-green-500">
                                Close Maintenance Service
                            </button>
                        </form>
                    @else
                        <div class="rounded-xl border border-dashed border-gray-300 px-4 py-6 text-sm text-gray-600 dark:border-gray-700/60 dark:text-gray-300">
                            {{ $service->notes ?: 'No notes have been recorded for this service.' }}
                        </div>
                    @endif
                </div>
            </section>

            @if ($service->isLocationService())
            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Machines At This Location</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Count and fill each machine after the service has been opened.</p>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700/60">
                        <thead class="bg-gray-50 dark:bg-gray-800/80">
                            <tr>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Machine</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Serial</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Model</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Status</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Bins</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700/60">
                            @forelse ($service->location?->machines ?? collect() as $machine)
                                <tr class="bg-white dark:bg-gray-800">
                                    <td class="px-5 py-4 font-medium text-gray-800 dark:text-gray-100">{{ $machine->type }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $machine->serial_number ?: '—' }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $machine->model ?: '—' }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $machine->status }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $machine->bins->count() }}</td>
                                    <td class="px-5 py-4">
                                        @if ($service->isServiceOpen())
                                            <div class="flex flex-wrap items-center gap-2">
                                                <a href="{{ route('services.machines.count', [$service->id, $machine->id]) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                                                    Count Machine
                                                </a>
                                                <a href="{{ route('services.machines.fill', [$service->id, $machine->id]) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                                                    Fill Machine
                                                </a>
                                            </div>
                                        @else
                                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                                {{ $service->isAwaitingService() ? 'Open service to begin.' : ($service->isServiceCompleted() ? 'Service completed.' : 'Service closed.') }}
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr class="bg-white dark:bg-gray-800">
                                    <td colspan="6" class="px-5 py-8 text-center text-gray-500 dark:text-gray-400">
                                        No machines are assigned to this location.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Transactions</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Transactions grouped by date and type, with the most recent activity shown first.</p>
                    </div>
                </div>
                <div class="panel-body space-y-3">
                    @php
                        $transactionTypeLabels = [
                            \App\Models\Transaction::TYPE_CURRENT_INVENTORY => 'Current Inventory',
                            'count' => 'Count',
                            'fill' => 'Fill',
                            'add' => 'Add',
                            'waste' => 'Waste',
                            'remove' => 'Remove',
                            'adjustment' => 'Adjustment',
                        ];
                    @endphp

                    @forelse ($transactionsByDateAndType as $date => $typeGroups)
                        @php
                            $dateCount = $typeGroups->sum(fn ($transactions) => $transactions->count());
                            $dateLabel = $date === 'Unknown Date'
                                ? 'Unknown Date'
                                : \App\Support\AppDateTime::displayDate(\Illuminate\Support\Carbon::createFromFormat('Y-m-d', $date));
                        @endphp

                        <div x-data="{ open: false }" class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700/60">
                            <button
                                type="button"
                                class="flex w-full items-center justify-between gap-4 bg-gray-50 px-4 py-3 text-left dark:bg-gray-800/80"
                                @click="open = !open"
                                :aria-expanded="open.toString()"
                            >
                                <div class="min-w-0">
                                    <div class="font-medium text-gray-800 dark:text-gray-100">{{ $dateLabel }}</div>
                                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        {{ $dateCount }} {{ \Illuminate\Support\Str::plural('transaction', $dateCount) }}
                                    </div>
                                </div>
                                <span class="inline-flex h-4 w-4 shrink-0 items-center justify-center text-sm leading-none text-gray-400 transition-transform duration-200" :class="open ? 'rotate-90' : ''" aria-hidden="true">›</span>
                            </button>

                            <div x-show="open" x-transition.origin.top.duration.200ms class="space-y-3 border-t border-gray-200 bg-white p-3 dark:border-gray-700/60 dark:bg-gray-900/30">
                                @foreach ($typeGroups as $type => $transactions)
                                    @php
                                        $typeLabel = $transactionTypeLabels[$type] ?? \Illuminate\Support\Str::headline((string) $type);
                                    @endphp

                                    <div x-data="{ open: false }" class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700/60">
                                        <button
                                            type="button"
                                            class="flex w-full items-center justify-between gap-4 bg-gray-50 px-4 py-3 text-left dark:bg-gray-800/80"
                                            @click="open = !open"
                                            :aria-expanded="open.toString()"
                                        >
                                            <div class="min-w-0">
                                                <div class="font-medium text-gray-800 dark:text-gray-100">{{ $typeLabel }}</div>
                                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                    {{ $transactions->count() }} {{ \Illuminate\Support\Str::plural('transaction', $transactions->count()) }}
                                                </div>
                                            </div>
                                            <span class="inline-flex h-4 w-4 shrink-0 items-center justify-center text-sm leading-none text-gray-400 transition-transform duration-200" :class="open ? 'rotate-90' : ''" aria-hidden="true">›</span>
                                        </button>

                                        <div x-show="open" x-transition.origin.top.duration.200ms>
                                            <div class="overflow-x-auto">
                                                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700/60">
                                                    <thead class="bg-white dark:bg-gray-800">
                                                        <tr>
                                                            <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Time</th>
                                                            <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Machine</th>
                                                            <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Bin</th>
                                                            <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Product</th>
                                                            <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Quantity</th>
                                                            <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Price</th>
                                                            <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Unit Cost</th>
                                                            <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700/60">
                                                        @foreach ($transactions as $transaction)
                                                            @php
                                                                $machineLabel = $transaction->machine?->serial_number
                                                                    ?: $transaction->machine?->model
                                                                    ?: $transaction->machine?->type
                                                                    ?: '—';
                                                                $price = $transaction->price !== null ? number_format((float) $transaction->price, 2) : '—';
                                                                $unitCost = $transaction->unit_cost !== null ? number_format((float) $transaction->unit_cost, 4) : '—';
                                                            @endphp

                                                            <tr class="bg-white dark:bg-gray-800">
                                                                <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ \App\Support\AppDateTime::displayTime($transaction->transaction_at) }}</td>
                                                                <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $machineLabel }}</td>
                                                                <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $transaction->bin?->bin_code ?? '—' }}</td>
                                                                <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $transaction->product?->product_name ?? '—' }}</td>
                                                                <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $transaction->quantity }}</td>
                                                                <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $price }}</td>
                                                                <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $unitCost }}</td>
                                                                <td class="px-5 py-4">
                                                                    <div class="flex flex-wrap items-center gap-2">
                                                                        <a href="{{ route('transactions.show', $transaction) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                                                                            View
                                                                        </a>

                                                                        @if ($service->isServiceOpen())
                                                                            <a href="{{ route('transactions.edit', $transaction) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                                                                                Edit
                                                                            </a>

                                                                            <form method="POST" action="{{ route('transactions.destroy', $transaction) }}" onsubmit="return confirm('Delete this transaction?');">
                                                                                @csrf
                                                                                @method('DELETE')
                                                                                <button type="submit" class="inline-flex items-center rounded-xl border border-red-300 px-3 py-1.5 text-xs font-medium text-red-700 transition hover:bg-red-50 dark:border-red-500/40 dark:text-red-300 dark:hover:bg-red-500/10">
                                                                                    Delete
                                                                                </button>
                                                                            </form>
                                                                        @endif
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <div class="rounded-xl border border-dashed border-gray-300 px-4 py-6 text-sm text-gray-500 dark:border-gray-700/60 dark:text-gray-400">
                            No transactions have been recorded for this service.
                        </div>
                    @endforelse
                </div>
            </section>
            @endif
        </div>
    </div>
</x-app-layout>
