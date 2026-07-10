<x-app-layout title="Add Product">
    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-3xl space-y-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 md:text-3xl">Add Product</h1>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Create a product for the selected account.</p>
                </div>
                <a href="{{ route('products.index') }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Back to Inventory</a>
            </div>
            <section class="panel"><div class="panel-body">
                <form method="POST" action="{{ route('products.store') }}" class="space-y-5">
                    @csrf
                    <div>
                        <x-label for="vendor_id" value="Vendor" />
                        <select id="vendor_id" name="vendor_id" class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100">
                            <option value="">No vendor</option>
                            @foreach ($vendors as $vendor)
                                <option value="{{ $vendor->id }}" @selected(old('vendor_id') == $vendor->id)>{{ $vendor->vendor_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid gap-5 md:grid-cols-2">
                        <div><x-label for="product_name" value="Product Name" /><x-input id="product_name" name="product_name" type="text" :value="old('product_name')" required /></div>
                        <div><x-label for="sku" value="SKU" /><x-input id="sku" name="sku" type="text" :value="old('sku')" /></div>
                    </div>
                    <div><x-label for="barcode" value="Barcode" /><x-input id="barcode" name="barcode" type="text" :value="old('barcode')" /></div>
                    <div class="flex items-center justify-end gap-3"><a href="{{ route('products.index') }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Cancel</a><x-button>Create Product</x-button></div>
                </form>
                <x-validation-errors class="mt-6" />
            </div></section>
        </div>
    </div>
</x-app-layout>
