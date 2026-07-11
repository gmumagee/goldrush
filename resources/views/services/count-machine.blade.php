<x-app-layout title="Count Machine">
    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-6xl space-y-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 md:text-3xl">Count Machine</h1>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        Service #{{ $service->id }} · {{ $machine->type }} · {{ $machine->serial_number ?: 'No serial' }}
                    </p>
                </div>

                <a href="{{ route('services.show', $service->id) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                    Back to Service
                </a>
            </div>

            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">{{ $machine->type }}</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Enter the counted quantity for each bin on this machine.</p>
                    </div>
                </div>
                <div class="panel-body">
                    <form method="POST" action="{{ route('services.machines.count.store', [$service->id, $machine->id]) }}" class="space-y-5">
                        @csrf

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700/60">
                                <thead class="bg-gray-50 dark:bg-gray-800/80">
                                    <tr>
                                        <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Bin</th>
                                        <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Product</th>
                                        <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Capacity</th>
                                        <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Price</th>
                                        <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Count Quantity</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700/60">
                                    @forelse ($machine->bins as $bin)
                                        <tr class="bg-white dark:bg-gray-800">
                                            <td class="px-5 py-4 font-medium text-gray-800 dark:text-gray-100">{{ $bin->bin_code }}</td>
                                            <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $bin->product?->product_name ?? '—' }}</td>
                                            <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $bin->capacity }}</td>
                                            <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $bin->price !== null ? number_format((float) $bin->price, 2) : '—' }}</td>
                                            <td class="px-5 py-4">
                                                <input
                                                    type="number"
                                                    min="0"
                                                    max="{{ $bin->capacity ?: '' }}"
                                                    name="quantities[{{ $bin->id }}]"
                                                    value="{{ old('quantities.'.$bin->id, 0) }}"
                                                    class="block w-28 rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                                                    required
                                                >
                                            </td>
                                        </tr>
                                    @empty
                                        <tr class="bg-white dark:bg-gray-800">
                                            <td colspan="5" class="px-5 py-8 text-center text-gray-500 dark:text-gray-400">
                                                No bins have been added to this machine yet.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="flex items-center justify-end gap-3">
                            <a href="{{ route('services.show', $service->id) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                                Cancel
                            </a>
                            <x-button :disabled="$machine->bins->isEmpty()">Count Machine</x-button>
                        </div>
                    </form>

                    <x-validation-errors class="mt-6" />
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
