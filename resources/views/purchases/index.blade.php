<x-app-layout title="Purchases">
    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-7xl space-y-6">
            @php
                $statusClasses = static function (?string $status): string {
                    return match (strtolower(trim((string) $status))) {
                        strtolower(\App\Models\Purchase::STATUS_POSTED) => 'bg-blue-100 text-blue-800 dark:bg-blue-500/15 dark:text-blue-300',
                        strtolower(\App\Models\Purchase::STATUS_VOIDED) => 'bg-red-100 text-red-800 dark:bg-red-500/15 dark:text-red-300',
                        default => 'bg-gray-100 text-gray-700 dark:bg-gray-700/60 dark:text-gray-200',
                    };
                };
                $displayStatus = static function (?string $status) use ($purchaseStatusLabels): string {
                    $normalizedStatus = strtolower(trim((string) $status));

                    return $purchaseStatusLabels[$normalizedStatus] ?? ($status ?: 'Unknown');
                };
            @endphp

            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 md:text-3xl">Purchases</h1>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Post inbound inventory purchases and maintain warehouse inventory history.</p>
                </div>
                <a href="{{ route('purchases.create') }}" class="inline-flex items-center rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-violet-500">Create Purchase</a>
            </div>

            @if (session('status'))
                <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-900/60 dark:bg-green-500/10 dark:text-green-300">{{ session('status') }}</div>
            @endif

            <x-validation-errors />

            <section class="panel">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700/60">
                        <thead class="bg-gray-50 dark:bg-gray-800/80">
                            <tr>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Purchase ID</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Purchase Date</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Vendor</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Warehouse</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Invoice Number</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Status</th>
                                <th class="px-5 py-3 text-right font-medium text-gray-500 dark:text-gray-400">Total Amount</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Created Date</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Created Time</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700/60">
                            @forelse ($purchases as $purchase)
                                <tr class="bg-white dark:bg-gray-800">
                                    <td class="px-5 py-4 font-medium text-gray-800 dark:text-gray-100">#{{ $purchase->id }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ \App\Support\AppDateTime::displayDate($purchase->purchase_date) }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $purchase->vendor?->vendor_name ?? '—' }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $purchase->warehouse?->warehouse_name ?? '—' }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $purchase->invoice_number ?: '—' }}</td>
                                    <td class="px-5 py-4"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $statusClasses($purchase->status) }}">{{ $displayStatus($purchase->status) }}</span></td>
                                    <td class="px-5 py-4 text-right tabular-nums text-gray-600 dark:text-gray-300">{{ number_format((float) ($purchase->total_amount ?? 0), 2) }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ \App\Support\AppDateTime::displayDate($purchase->created_at) }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ \App\Support\AppDateTime::displayTime($purchase->created_at) }}</td>
                                    <td class="px-5 py-4">
                                        <div class="flex flex-wrap gap-2">
                                            <a href="{{ route('purchases.show', $purchase) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">View</a>
                                            @if ($purchase->isPosted())
                                                <form method="POST" action="{{ route('purchases.void', $purchase) }}">
                                                    @csrf
                                                    <button type="submit" class="inline-flex items-center rounded-xl border border-red-300 px-3 py-1.5 text-xs font-medium text-red-700 transition hover:bg-red-50 dark:border-red-500/40 dark:text-red-300 dark:hover:bg-red-500/10">Void Purchase</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr class="bg-white dark:bg-gray-800">
                                    <td colspan="10" class="px-5 py-8 text-center text-gray-500 dark:text-gray-400">No purchases have been posted for this account.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="panel-body">{{ $purchases->links() }}</div>
            </section>
        </div>
    </div>
</x-app-layout>
