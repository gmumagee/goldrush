<x-app-layout title="Edit Bins">
    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-7xl space-y-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 md:text-3xl">Edit Bins</h1>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        {{ $machine->type }} · {{ $machine->serial_number ?: 'No serial' }} · {{ $machine->location?->location_name ?? 'No location' }}
                    </p>
                </div>

                <div class="flex gap-3">
                    <a href="{{ route('bins.index') }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                        All Bins
                    </a>
                    <a href="{{ route('machines.show', $machine) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                        Back to Machine
                    </a>
                </div>
            </div>

            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Bulk Bin Editor</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Update bin codes, assigned products, capacities, and prices for this machine.</p>
                    </div>
                </div>
                <div class="panel-body">
                    <form method="POST" action="{{ route('machines.bins.update', $machine) }}" class="space-y-5">
                        @csrf
                        @method('PATCH')

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700/60">
                                <thead class="bg-gray-50 dark:bg-gray-800/80">
                                    <tr>
                                        <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Bin</th>
                                        <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Product</th>
                                        <th class="px-5 py-3 text-right font-medium text-gray-500 dark:text-gray-400">Capacity</th>
                                        <th class="px-5 py-3 text-right font-medium text-gray-500 dark:text-gray-400">Price</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700/60">
                                    @forelse ($machine->bins as $bin)
                                        <tr class="bg-white align-top dark:bg-gray-800">
                                            <td class="px-5 py-4">
                                                <x-input
                                                    :id="'bin_code_'.$bin->id"
                                                    :name="'bins['.$bin->id.'][bin_code]'"
                                                    type="text"
                                                    :value="old('bins.'.$bin->id.'.bin_code', $bin->bin_code)"
                                                    required
                                                />
                                            </td>
                                            <td class="px-5 py-4">
                                                <select id="product_{{ $bin->id }}" name="bins[{{ $bin->id }}][product_id]" class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100">
                                                    <option value="">No product</option>
                                                    @foreach ($products as $product)
                                                        <option value="{{ $product->id }}" @selected((string) old('bins.'.$bin->id.'.product_id', $bin->product_id) === (string) $product->id)>
                                                            {{ $product->display_name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td class="px-5 py-4">
                                                <x-input
                                                    :id="'capacity_'.$bin->id"
                                                    :name="'bins['.$bin->id.'][capacity]'"
                                                    type="number"
                                                    min="0"
                                                    :value="old('bins.'.$bin->id.'.capacity', $bin->capacity)"
                                                    required
                                                />
                                            </td>
                                            <td class="px-5 py-4">
                                                <x-input
                                                    :id="'price_'.$bin->id"
                                                    :name="'bins['.$bin->id.'][price]'"
                                                    type="number"
                                                    min="0"
                                                    step="0.01"
                                                    :value="old('bins.'.$bin->id.'.price', $bin->price)"
                                                />
                                            </td>
                                        </tr>
                                    @empty
                                        <tr class="bg-white dark:bg-gray-800">
                                            <td colspan="4" class="px-5 py-8 text-center text-gray-500 dark:text-gray-400">
                                                No bins have been added to this machine yet.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="flex items-center justify-end gap-3">
                            <a href="{{ route('machines.show', $machine) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                                Cancel
                            </a>
                            <x-button>Save Bins</x-button>
                        </div>
                    </form>

                    <x-validation-errors class="mt-6" />
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
