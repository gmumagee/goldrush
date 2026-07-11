<x-app-layout title="Service Details">
    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-7xl space-y-6">
            @php
                $statusClasses = match ($service->status) {
                    \App\Models\Service::STATUS_AWAITING_SERVICE => 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300',
                    \App\Models\Service::STATUS_SERVICE_OPEN => 'bg-blue-100 text-blue-800 dark:bg-blue-500/15 dark:text-blue-300',
                    \App\Models\Service::STATUS_SERVICE_CLOSED => 'bg-green-100 text-green-800 dark:bg-green-500/15 dark:text-green-300',
                    default => 'bg-gray-100 text-gray-700 dark:bg-gray-700/60 dark:text-gray-200',
                };
            @endphp

            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 md:text-3xl">Service #{{ $service->id }}</h1>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        {{ $service->location?->location_name ?? 'No location' }} · {{ $service->service_date?->format('Y-m-d') ?? 'No date' }}
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
                        <form method="POST" action="{{ route('services.close', $service->id) }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center rounded-xl bg-green-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-green-500">
                                Close Service
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            @if (session('status'))
                <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-900/60 dark:bg-green-500/10 dark:text-green-300">
                    {{ session('status') }}
                </div>
            @endif

            <x-validation-errors />

            <div class="grid gap-6 xl:grid-cols-3">
                <section class="panel xl:col-span-1">
                    <div class="panel-header">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Service Summary</h2>
                        </div>
                    </div>
                    <div class="panel-body">
                        <dl class="space-y-4 text-sm">
                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">Status</dt>
                                <dd class="mt-1">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $statusClasses }}">
                                        {{ $service->status }}
                                    </span>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">Service Date</dt>
                                <dd class="mt-1 font-medium text-gray-800 dark:text-gray-100">{{ $service->service_date?->format('Y-m-d') ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">Opened At</dt>
                                <dd class="mt-1 font-medium text-gray-800 dark:text-gray-100">{{ $service->opened_at?->format('Y-m-d H:i') ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">Closed At</dt>
                                <dd class="mt-1 font-medium text-gray-800 dark:text-gray-100">{{ $service->closed_at?->format('Y-m-d H:i') ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">Assigned User</dt>
                                <dd class="mt-1 font-medium text-gray-800 dark:text-gray-100">{{ $service->user?->name ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">Route</dt>
                                <dd class="mt-1 font-medium text-gray-800 dark:text-gray-100">{{ $service->location?->route?->route_name ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">Address</dt>
                                <dd class="mt-1 font-medium text-gray-800 dark:text-gray-100">{{ $service->location?->address ?: '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">City</dt>
                                <dd class="mt-1 font-medium text-gray-800 dark:text-gray-100">{{ $service->location?->city ?: '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">Contact</dt>
                                <dd class="mt-1 font-medium text-gray-800 dark:text-gray-100">{{ $service->location?->contact_name ?: '—' }}</dd>
                            </div>
                        </dl>
                    </div>
                </section>

                <section class="panel xl:col-span-2">
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
                                                    {{ $service->isAwaitingService() ? 'Open service to begin.' : 'Service closed.' }}
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
            </div>

            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Service Transactions</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Read-only history of count and fill transactions recorded for this service visit.</p>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700/60">
                        <thead class="bg-gray-50 dark:bg-gray-800/80">
                            <tr>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Type</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Machine</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Bin</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Product</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Quantity</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Transaction At</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700/60">
                            @forelse ($service->transactions as $transaction)
                                <tr class="bg-white dark:bg-gray-800">
                                    <td class="px-5 py-4 text-gray-600 capitalize dark:text-gray-300">{{ $transaction->transaction_type }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $transaction->bin?->machine?->type ?? '—' }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $transaction->bin?->bin_code ?? '—' }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $transaction->product?->product_name ?? '—' }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $transaction->quantity }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $transaction->transaction_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr class="bg-white dark:bg-gray-800">
                                    <td colspan="6" class="px-5 py-8 text-center text-gray-500 dark:text-gray-400">
                                        No transactions have been recorded for this service yet.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
