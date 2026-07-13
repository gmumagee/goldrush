<x-app-layout title="Enter Amount Collected">
    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-3xl space-y-6">
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
