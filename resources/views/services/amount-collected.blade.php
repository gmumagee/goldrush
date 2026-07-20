<x-app-layout title="Enter Amount Collected">
        <div class="px-4 py-8 sm:px-6 lg:px-8">
            <div class="mx-auto w-full max-w-3xl space-y-6">
                @php
                    // Reuse the stored reconciliation counts so close-out messaging matches the completed service.
                    $calculatedSalesCount = (int) ($service->calculated_sales_count ?? 0);
                    $baselineSalesCount = (int) ($service->baseline_sales_count ?? 0);
                    $reconciliationStatus = match (true) {
                    $calculatedSalesCount > 0 && $baselineSalesCount === 0 => \App\Models\Service::RECONCILIATION_COMPLETE,
                    $calculatedSalesCount > 0 && $baselineSalesCount > 0 => \App\Models\Service::RECONCILIATION_PARTIAL,
                    $calculatedSalesCount === 0 && $baselineSalesCount > 0 => \App\Models\Service::RECONCILIATION_BASELINE_ONLY,
                    default => \App\Models\Service::RECONCILIATION_NONE,
                };
                $salesSummary = match ($reconciliationStatus) {
                    \App\Models\Service::RECONCILIATION_COMPLETE => 'Finalized Sales: '.\App\Support\Money::format((string) $service->sales_total),
                    \App\Models\Service::RECONCILIATION_PARTIAL => 'Calculated Sales Subtotal: '.\App\Support\Money::format((string) $service->sales_total).' ('.\App\Models\Service::reconciliationStatusLabel($reconciliationStatus).')',
                    \App\Models\Service::RECONCILIATION_BASELINE_ONLY => 'Finalized Sales: Initial installation — no prior inventory exists, so sales cannot be calculated for this service.',
                    default => null,
                };
            @endphp

            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 md:text-3xl">Enter Amount Collected</h1>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        Service #{{ $service->id }} · {{ $service->location?->location_name ?? 'No location' }}
                    </p>
                </div>

                <a href="{{ route('services.show', $service) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                    Back to Service
                </a>
            </div>

            <x-validation-errors />

            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Close Service</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Record the final amount collected to fully close this completed service.</p>
                    </div>
                </div>
                <div class="panel-body">
                    <form method="POST" action="{{ route('services.amount-collected.update', $service) }}" class="space-y-5">
                        @csrf

                        @if ($salesSummary)
                            <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700 dark:border-gray-700/60 dark:bg-gray-800/70 dark:text-gray-200">
                                {{ $salesSummary }}
                            </div>
                        @endif

                        @if (in_array($reconciliationStatus, [\App\Models\Service::RECONCILIATION_PARTIAL, \App\Models\Service::RECONCILIATION_BASELINE_ONLY], true))
                            <div class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-700 dark:border-blue-500/30 dark:bg-blue-500/10 dark:text-blue-200">
                                Financial difference is unavailable because one or more bins are being recorded as an initial installation during this service.
                            </div>
                        @endif

                        <div>
                            <x-label for="amount_collected" value="Amount Collected" />
                            <x-input
                                id="amount_collected"
                                name="amount_collected"
                                type="number"
                                min="0"
                                step="0.01"
                                :value="old('amount_collected')"
                                required
                            />
                        </div>

                        <div class="flex items-center justify-end gap-3">
                            <a href="{{ route('services.show', $service) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                                Cancel
                            </a>
                            <x-button>Close Service</x-button>
                        </div>
                    </form>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
