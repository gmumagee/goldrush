<x-app-layout title="Service Details">
    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-7xl space-y-6">
            @php
                $statusClasses = match (strtolower(trim((string) $service->status))) {
                    strtolower(\App\Models\Service::STATUS_AWAITING_SERVICE) => 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300',
                    strtolower(\App\Models\Service::STATUS_SERVICE_OPEN) => 'bg-blue-100 text-blue-800 dark:bg-blue-500/15 dark:text-blue-300',
                    strtolower(\App\Models\Service::STATUS_SERVICE_COMPLETED) => 'bg-violet-100 text-violet-800 dark:bg-violet-500/15 dark:text-violet-300',
                    strtolower(\App\Models\Service::STATUS_SERVICE_CLOSED) => 'bg-green-100 text-green-800 dark:bg-green-500/15 dark:text-green-300',
                    default => 'bg-gray-100 text-gray-700 dark:bg-gray-700/60 dark:text-gray-200',
                };
                $statusLabel = $serviceStatusLabels[strtolower(trim((string) $service->status))] ?? ($service->status ?: 'Unknown');
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

                    @if ($service->isAwaitingService())
                        <form method="POST" action="{{ route('services.open', $service->id) }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-blue-500">
                                Open Service
                            </button>
                        </form>
                    @endif

                    @if ($service->isServiceOpen())
                        <form method="POST" action="{{ route('services.complete', $service->id) }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center rounded-xl bg-green-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-green-500">
                                Complete Service
                            </button>
                        </form>
                    @endif

                    @if ($service->isServiceCompleted() && $service->amount_collected === null)
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
                                <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Status</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Source Warehouse</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Service Date</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Opened At</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Completed At</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Closed At</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Closed By</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Amount Collected</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Assigned User</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700/60">
                            <tr class="bg-white dark:bg-gray-800">
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $statusClasses }}">
                                        {{ $statusLabel }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $service->warehouse?->warehouse_name ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ \App\Support\AppDateTime::displayDate($service->service_date) }}</td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ \App\Support\AppDateTime::displayTime($service->opened_at) }}</td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ \App\Support\AppDateTime::displayTime($service->completed_at) }}</td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ \App\Support\AppDateTime::displayTime($service->closed_at) }}</td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $service->closedBy?->name ?? 'Not closed yet' }}</td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                    @if ($service->amount_collected !== null)
                                        {{ number_format((float) $service->amount_collected, 2) }}
                                    @elseif ($service->isServiceCompleted())
                                        Pending
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
        </div>
    </div>
</x-app-layout>
