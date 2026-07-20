<x-app-layout title="Edit Transaction">
    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-3xl space-y-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 md:text-3xl">Edit Transaction</h1>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Update this transaction while its service remains editable.</p>
                </div>

                <a href="{{ route('transactions.show', $transaction) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                    Back to Transaction
                </a>
            </div>

            <section class="panel">
                <div class="panel-body">
                    <form method="POST" action="{{ route('transactions.update', $transaction) }}" class="space-y-5">
                        @csrf
                        @method('PATCH')

                        <div>
                            <x-label for="service_id" value="Service" />
                            <select id="service_id" name="service_id" class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100" required>
                                @foreach ($services as $service)
                                    <option value="{{ $service->id }}" @selected(old('service_id', $transaction->service_id) == $service->id)>
                                        #{{ $service->id }} · {{ $service->location?->location_name ?? 'No location' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <x-label for="machine_id" value="Machine" />
                            <select id="machine_id" name="machine_id" class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100" required>
                                @foreach ($machines as $machine)
                                    <option value="{{ $machine->id }}" @selected(old('machine_id', $transaction->machine_id) == $machine->id)>
                                        {{ $machine->type }} · {{ $machine->serial_number ?: 'No serial' }} · {{ $machine->location?->location_name ?? 'No location' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <x-label for="bin_id" value="Bin" />
                            <select id="bin_id" name="bin_id" class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100" required>
                                @foreach ($bins as $bin)
                                    <option value="{{ $bin->id }}" @selected(old('bin_id', $transaction->bin_id) == $bin->id)>
                                        {{ $bin->machine?->type ?? 'Machine' }} · {{ $bin->bin_code }} · {{ $bin->product?->product_name ?? 'No product' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600 dark:border-gray-700/60 dark:bg-gray-900/40 dark:text-gray-300">
                            Product is derived from the selected bin. Fill transactions cannot be edited once they affect warehouse inventory.
                        </div>

                        <div class="grid gap-5 md:grid-cols-3">
                            <div>
                                <x-label for="transaction_type" value="Transaction Type" />
                                <select id="transaction_type" name="transaction_type" class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100" required>
                                    @foreach ($transactionTypes as $type)
                                        <option value="{{ $type }}" @selected(old('transaction_type', $transaction->transaction_type) === $type)>{{ ucfirst($type) }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <x-label for="quantity" value="Quantity" />
                                <x-input id="quantity" name="quantity" type="number" :value="old('quantity', $transaction->quantity)" required />
                            </div>

                            <div>
                                <x-label for="spoilage" value="Spoilage" />
                                {{-- Keep the stored spoilage visible so editing a count does not silently reset it to zero. --}}
                                <x-input id="spoilage" name="spoilage" type="number" min="0" step="1" :value="old('spoilage', $transaction->spoilage ?? 0)" />
                                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Use this only for Count transactions. Leave 0 for other transaction types.</p>
                            </div>
                        </div>

                        <div class="grid gap-5 md:grid-cols-3">
                            <div>
                                <x-label for="price" value="Price" />
                                <x-input id="price" name="price" type="number" min="0" step="0.01" :value="old('price', $transaction->price)" />
                            </div>

                            <div>
                                <x-label for="transaction_date" value="Transaction Date" />
                                <x-input id="transaction_date" name="transaction_date" type="text" placeholder="MM-DD-YYYY" :value="old('transaction_date', \App\Support\AppDateTime::inputDate($transaction->transaction_at))" required />
                            </div>

                            <div>
                                <x-label for="transaction_time" value="Transaction Time" />
                                <x-input id="transaction_time" name="transaction_time" type="text" placeholder="HH:MM:SS" :value="old('transaction_time', \App\Support\AppDateTime::inputTime($transaction->transaction_at))" required />
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-3">
                            <a href="{{ route('transactions.show', $transaction) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                                Cancel
                            </a>

                            <x-button>Save Transaction</x-button>
                        </div>
                    </form>

                    <x-validation-errors class="mt-6" />
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
