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
                                        {{-- Keep the spoilage rules available without repeating the same text in every row. --}}
                                        <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">
                                            <span class="inline-flex items-center gap-1">
                                                Spoilage
                                                <button
                                                    type="button"
                                                    class="count-column-help inline-flex h-4 w-4 items-center justify-center rounded-full text-xs text-gray-400 transition hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-violet-500 dark:text-gray-500 dark:hover:text-gray-300"
                                                    data-bs-toggle="tooltip"
                                                    data-bs-placement="top"
                                                    title="Enter expired, damaged, or otherwise unsellable products removed from the bin."
                                                    aria-label="About Spoilage"
                                                >
                                                    <span aria-hidden="true">ⓘ</span>
                                                </button>
                                            </span>
                                        </th>
                                        {{-- Keep the count guidance in the heading so each row stays compact and readable. --}}
                                        <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">
                                            <span class="inline-flex items-center gap-1">
                                                Count
                                                <button
                                                    type="button"
                                                    class="count-column-help inline-flex h-4 w-4 items-center justify-center rounded-full text-xs text-gray-400 transition hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-violet-500 dark:text-gray-500 dark:hover:text-gray-300"
                                                    data-bs-toggle="tooltip"
                                                    data-bs-placement="top"
                                                    title="Enter only usable, saleable products remaining after spoilage has been removed."
                                                    aria-label="About Count"
                                                >
                                                    <span aria-hidden="true">ⓘ</span>
                                                </button>
                                            </span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700/60">
                                    @forelse ($machine->bins as $bin)
                                        @php
                                            // Reuse the latest persisted count row so reloads and corrections keep prior values visible.
                                            $existingCountTransaction = $countTransactionsByBin->get($bin->id);
                                        @endphp
                                        <tr class="bg-white dark:bg-gray-800">
                                            <td class="px-5 py-4 font-medium text-gray-800 dark:text-gray-100">{{ $bin->bin_code }}</td>
                                            <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $bin->product?->product_name ?? '—' }}</td>
                                            <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $bin->capacity }}</td>
                                            <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $bin->price !== null ? number_format((float) $bin->price, 2) : '—' }}</td>
                                            {{-- Render spoilage before count so the table matches the revised count workflow. --}}
                                            <td class="px-5 py-4">
                                                <input
                                                    id="spoilage-{{ $bin->id }}"
                                                    type="number"
                                                    min="0"
                                                    step="1"
                                                    name="counts[{{ $bin->id }}][spoilage]"
                                                    value="{{ old('counts.'.$bin->id.'.spoilage', $existingCountTransaction?->spoilage ?? 0) }}"
                                                    class="block w-28 rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                                                    required
                                                >
                                                @error('counts.'.$bin->id.'.spoilage')
                                                    <p class="mt-2 text-xs text-red-600 dark:text-red-400">
                                                        {{ $message }}
                                                    </p>
                                                @enderror
                                            </td>
                                            {{-- Keep the quantity field name unchanged so count persistence and sales logic stay intact. --}}
                                            <td class="px-5 py-4">
                                                <input
                                                    id="count-{{ $bin->id }}"
                                                    type="number"
                                                    min="0"
                                                    max="{{ $bin->capacity ?: '' }}"
                                                    name="counts[{{ $bin->id }}][quantity]"
                                                    value="{{ old('counts.'.$bin->id.'.quantity', $existingCountTransaction?->quantity ?? 0) }}"
                                                    class="block w-28 rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                                                    required
                                                >
                                                @error('counts.'.$bin->id.'.quantity')
                                                    <p class="mt-2 text-xs text-red-600 dark:text-red-400">
                                                        {{ $message }}
                                                    </p>
                                                @enderror
                                            </td>
                                        </tr>
                                    @empty
                                        <tr class="bg-white dark:bg-gray-800">
                                            <td colspan="6" class="px-5 py-8 text-center text-gray-500 dark:text-gray-400">
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
