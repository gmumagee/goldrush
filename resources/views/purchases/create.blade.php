<x-app-layout title="Create Purchase">
    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-6xl space-y-6">
            @php
                $initialRows = old('items', [['product_id' => '', 'quantity' => '', 'line_total' => '']]);
                $productOptions = $products->map(fn ($product) => [
                    'id' => $product->id,
                    'label' => $product->display_name,
                ])->values();
            @endphp

            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 md:text-3xl">Create Purchase</h1>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Post inventory into a warehouse immediately. Posted purchases are read-only and must be voided to correct mistakes.</p>
                </div>
                <a href="{{ route('purchases.index') }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Back to Purchases</a>
            </div>

            <section class="panel">
                <div class="panel-body">
                    @if ($warehouses->isEmpty() || $products->isEmpty())
                        <div class="rounded-xl border border-yellow-200 bg-yellow-50 px-4 py-3 text-sm text-yellow-800 dark:border-yellow-900/60 dark:bg-yellow-500/10 dark:text-yellow-300">
                            Purchases require at least one warehouse and one product in the current account.
                        </div>
                    @endif

                    <form method="POST" action="{{ route('purchases.store') }}" class="space-y-6" x-data='{
                        rows: @json($initialRows),
                        productOptions: @json($productOptions),
                        addRow() {
                            this.rows.push({ product_id: "", quantity: "", line_total: "" });
                        },
                        removeRow(index) {
                            if (this.rows.length === 1) {
                                this.rows[0] = { product_id: "", quantity: "", line_total: "" };
                                return;
                            }

                            this.rows.splice(index, 1);
                        },
                        unitCost(row) {
                            const quantity = Number(row.quantity);
                            const lineTotal = Number(row.line_total);

                            if (!quantity || quantity <= 0 || Number.isNaN(lineTotal)) {
                                return "0.0000";
                            }

                            return (lineTotal / quantity).toFixed(4);
                        }
                    }'>
                        @csrf

                        <div class="grid gap-5 md:grid-cols-2">
                            <div>
                                <x-label for="vendor_id" value="Vendor" />
                                <select id="vendor_id" name="vendor_id" class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100">
                                    <option value="">No vendor</option>
                                    @foreach ($vendors as $vendor)
                                        <option value="{{ $vendor->id }}" @selected(old('vendor_id') == $vendor->id)>{{ $vendor->vendor_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <x-label for="warehouse_id" value="Warehouse" />
                                <select id="warehouse_id" name="warehouse_id" class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100" required>
                                    <option value="">Select a warehouse</option>
                                    @foreach ($warehouses as $warehouse)
                                        <option value="{{ $warehouse->id }}" @selected(old('warehouse_id', request('warehouse_id')) == $warehouse->id)>{{ $warehouse->warehouse_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="grid gap-5 md:grid-cols-2">
                            <div>
                                <x-label for="invoice_number" value="Invoice Number" />
                                <x-input id="invoice_number" name="invoice_number" type="text" :value="old('invoice_number')" />
                            </div>
                            <div>
                                <x-label for="purchase_date" value="Purchase Date" />
                                <x-input id="purchase_date" name="purchase_date" type="text" placeholder="MM-DD-YYYY" :value="old('purchase_date', \App\Support\AppDateTime::inputDate(now()))" required />
                            </div>
                        </div>

                        <div>
                            <x-label for="notes" value="Notes" />
                            <textarea id="notes" name="notes" rows="3" class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100">{{ old('notes') }}</textarea>
                        </div>

                        <div class="rounded-2xl border border-gray-200 dark:border-gray-700/60">
                            <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-700/60">
                                <div>
                                    <h2 class="text-base font-semibold text-gray-800 dark:text-gray-100">Purchase Items</h2>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Enter quantity and line total. Unit cost is calculated automatically.</p>
                                </div>
                                <button type="button" @click="addRow()" class="inline-flex items-center rounded-xl border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Add Item</button>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700/60">
                                    <thead class="bg-gray-50 dark:bg-gray-800/80">
                                        <tr>
                                            <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Product</th>
                                            <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Quantity</th>
                                            <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Line Total</th>
                                            <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Unit Cost Preview</th>
                                            <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700/60">
                                        <template x-for="(row, index) in rows" :key="index">
                                            <tr class="bg-white dark:bg-gray-800">
                                                <td class="px-4 py-3">
                                                    <select :name="`items[${index}][product_id]`" x-model="row.product_id" class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100" required>
                                                        <option value="">Select a product</option>
                                                        <template x-for="product in productOptions" :key="product.id">
                                                            <option :value="product.id" x-text="product.label"></option>
                                                        </template>
                                                    </select>
                                                </td>
                                                <td class="px-4 py-3">
                                                    <input :name="`items[${index}][quantity]`" x-model="row.quantity" type="number" min="1" class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100" required>
                                                </td>
                                                <td class="px-4 py-3">
                                                    <input :name="`items[${index}][line_total]`" x-model="row.line_total" type="number" min="0" step="0.01" class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100" required>
                                                </td>
                                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                                    <span x-text="unitCost(row)"></span>
                                                </td>
                                                <td class="px-4 py-3">
                                                    <button type="button" @click="removeRow(index)" class="inline-flex items-center rounded-xl border border-red-300 px-3 py-1.5 text-xs font-medium text-red-700 transition hover:bg-red-50 dark:border-red-500/40 dark:text-red-300 dark:hover:bg-red-500/10">Remove</button>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-3">
                            <a href="{{ route('purchases.index') }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Cancel</a>
                            <x-button :disabled="$warehouses->isEmpty() || $products->isEmpty()">Post Purchase</x-button>
                        </div>
                    </form>

                    <x-validation-errors class="mt-6" />
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
