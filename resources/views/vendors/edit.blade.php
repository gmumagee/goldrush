<x-app-layout title="Edit Vendor">
    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-3xl space-y-6">
            <div class="flex items-center justify-between gap-4">
                <div><h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 md:text-3xl">Edit Vendor</h1><p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Update vendor details for the selected account.</p></div>
                <a href="{{ route('vendors.show', $vendor) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Back to Vendor</a>
            </div>
            <section class="panel"><div class="panel-body">
                <form method="POST" action="{{ route('vendors.update', $vendor) }}" class="space-y-5">
                    @csrf
                    @method('PATCH')
                    <div><x-label for="vendor_name" value="Vendor Name" /><x-input id="vendor_name" name="vendor_name" type="text" :value="old('vendor_name', $vendor->vendor_name)" required /></div>
                    <div><x-label for="location" value="Location" /><x-input id="location" name="location" type="text" :value="old('location', $vendor->location)" /></div>
                    <div class="flex items-center justify-end gap-3"><a href="{{ route('vendors.show', $vendor) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Cancel</a><x-button>Save Vendor</x-button></div>
                </form>
                <x-validation-errors class="mt-6" />
            </div></section>
        </div>
    </div>
</x-app-layout>
