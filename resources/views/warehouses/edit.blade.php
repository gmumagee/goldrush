<x-app-layout title="Edit Warehouse">
    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-3xl space-y-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 md:text-3xl">Edit Warehouse</h1>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Update warehouse details for the selected account.</p>
                </div>
                <a href="{{ route('warehouses.show', $warehouse) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Back to Warehouse</a>
            </div>
            <section class="panel">
                <div class="panel-body">
                    <form method="POST" action="{{ route('warehouses.update', $warehouse) }}" class="space-y-5">
                        @csrf
                        @method('PATCH')
                        <div><x-label for="warehouse_name" value="Warehouse Name" /><x-input id="warehouse_name" name="warehouse_name" type="text" :value="old('warehouse_name', $warehouse->warehouse_name)" required /></div>
                        <div><x-label for="address" value="Address" /><x-input id="address" name="address" type="text" :value="old('address', $warehouse->address)" /></div>
                        <div class="grid gap-5 md:grid-cols-3">
                            <div><x-label for="city" value="City" /><x-input id="city" name="city" type="text" :value="old('city', $warehouse->city)" /></div>
                            <div><x-label for="state" value="State" /><x-input id="state" name="state" type="text" :value="old('state', $warehouse->state)" /></div>
                            <div><x-label for="zip_code" value="Zip Code" /><x-input id="zip_code" name="zip_code" type="text" :value="old('zip_code', $warehouse->zip_code)" /></div>
                        </div>
                        <div class="flex items-center justify-end gap-3">
                            <a href="{{ route('warehouses.show', $warehouse) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Cancel</a>
                            <x-button>Save Warehouse</x-button>
                        </div>
                    </form>
                    <x-validation-errors class="mt-6" />
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
