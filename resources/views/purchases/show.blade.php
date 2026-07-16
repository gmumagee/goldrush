<x-app-layout title="Purchase Details">
    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-6xl space-y-6">
            @php
                $statusLabel = $purchaseStatusLabels[strtolower(trim((string) $purchase->status))] ?? ($purchase->status ?: 'Unknown');
                $statusClasses = match (strtolower(trim((string) $purchase->status))) {
                    strtolower(\App\Models\Purchase::STATUS_POSTED) => 'bg-blue-100 text-blue-800 dark:bg-blue-500/15 dark:text-blue-300',
                    strtolower(\App\Models\Purchase::STATUS_VOIDED) => 'bg-red-100 text-red-800 dark:bg-red-500/15 dark:text-red-300',
                    default => 'bg-gray-100 text-gray-700 dark:bg-gray-700/60 dark:text-gray-200',
                };
            @endphp

            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 md:text-3xl">Purchase #{{ $purchase->id }}</h1>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ $purchase->warehouse?->warehouse_name ?? 'No warehouse' }}</p>
                </div>
                <div class="flex gap-3">
                    <a href="{{ route('purchases.index') }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Back to Purchases</a>
                    <a href="{{ route('calendar-events.create', ['source_type' => 'purchase', 'source_id' => $purchase->id]) }}" class="inline-flex items-center rounded-xl border border-violet-300 px-4 py-2 text-sm font-medium text-violet-700 transition hover:bg-violet-50 dark:border-violet-500/40 dark:text-violet-300 dark:hover:bg-violet-500/10">Schedule Event</a>
                    @if ($purchase->isPosted())
                        <form method="POST" action="{{ route('purchases.void', $purchase) }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center rounded-xl bg-red-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-red-500">Void Purchase</button>
                        </form>
                    @endif
                </div>
            </div>

            @if (session('status'))
                <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-900/60 dark:bg-green-500/10 dark:text-green-300">{{ session('status') }}</div>
            @endif

            <x-validation-errors />

            <section class="panel">
                <div class="panel-body">
                    <dl class="grid gap-4 text-sm md:grid-cols-2 xl:grid-cols-4">
                        <div><dt class="text-gray-500 dark:text-gray-400">Status</dt><dd class="mt-1"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $statusClasses }}">{{ $statusLabel }}</span></dd></div>
                        <div><dt class="text-gray-500 dark:text-gray-400">Purchase Date</dt><dd class="mt-1 text-gray-800 dark:text-gray-100">{{ \App\Support\AppDateTime::displayDate($purchase->purchase_date) }}</dd></div>
                        <div><dt class="text-gray-500 dark:text-gray-400">Vendor</dt><dd class="mt-1 text-gray-800 dark:text-gray-100">{{ $purchase->vendor?->vendor_name ?? '—' }}</dd></div>
                        <div><dt class="text-gray-500 dark:text-gray-400">Warehouse</dt><dd class="mt-1 text-gray-800 dark:text-gray-100">{{ $purchase->warehouse?->warehouse_name ?? '—' }}</dd></div>
                        <div><dt class="text-gray-500 dark:text-gray-400">Invoice Number</dt><dd class="mt-1 text-gray-800 dark:text-gray-100">{{ $purchase->invoice_number ?: '—' }}</dd></div>
                        <div><dt class="text-gray-500 dark:text-gray-400">Created Date</dt><dd class="mt-1 text-gray-800 dark:text-gray-100">{{ \App\Support\AppDateTime::displayDate($purchase->created_at) }}</dd></div>
                        <div><dt class="text-gray-500 dark:text-gray-400">Created Time</dt><dd class="mt-1 text-gray-800 dark:text-gray-100">{{ \App\Support\AppDateTime::displayTime($purchase->created_at) }}</dd></div>
                        <div><dt class="text-gray-500 dark:text-gray-400">Notes</dt><dd class="mt-1 text-gray-800 dark:text-gray-100">{{ $purchase->notes ?: '—' }}</dd></div>
                    </dl>
                </div>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Purchase Items</h2>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700/60">
                        <thead class="bg-gray-50 dark:bg-gray-800/80">
                            <tr>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Product</th>
                                <th class="px-5 py-3 text-right font-medium text-gray-500 dark:text-gray-400">Quantity</th>
                                <th class="px-5 py-3 text-right font-medium text-gray-500 dark:text-gray-400">Line Total</th>
                                <th class="px-5 py-3 text-right font-medium text-gray-500 dark:text-gray-400">Unit Cost</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700/60">
                            @foreach ($purchase->items as $item)
                                <tr class="bg-white dark:bg-gray-800">
                                    <td class="px-5 py-4 text-gray-800 dark:text-gray-100">{{ $item->product?->product_name ?? '—' }}</td>
                                    <td class="px-5 py-4 text-right tabular-nums text-gray-600 dark:text-gray-300">{{ $item->quantity }}</td>
                                    <td class="px-5 py-4 text-right tabular-nums text-gray-600 dark:text-gray-300">{{ number_format((float) $item->line_total, 2) }}</td>
                                    <td class="px-5 py-4 text-right tabular-nums text-gray-600 dark:text-gray-300">{{ number_format((float) $item->unit_cost, 4) }}</td>
                                </tr>
                            @endforeach
                            <tr class="bg-gray-50 dark:bg-gray-800/80">
                                <td class="px-5 py-4 font-medium text-gray-800 dark:text-gray-100">Total</td>
                                <td class="px-5 py-4"></td>
                                <td class="px-5 py-4 text-right font-medium tabular-nums text-gray-800 dark:text-gray-100">{{ number_format((float) $purchase->items->sum('line_total'), 2) }}</td>
                                <td class="px-5 py-4"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
