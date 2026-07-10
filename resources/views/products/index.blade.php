<x-app-layout title="Product Inventory">
    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-7xl space-y-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 md:text-3xl">Product Inventory</h1>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Manage products for the selected account.</p>
                </div>
                <a href="{{ route('products.create') }}" class="inline-flex items-center rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-violet-500">Add Product</a>
            </div>
            @if (session('status'))
                <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-900/60 dark:bg-green-500/10 dark:text-green-300">{{ session('status') }}</div>
            @endif
            <section class="panel">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700/60">
                        <thead class="bg-gray-50 dark:bg-gray-800/80"><tr><th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">ID</th><th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Name</th><th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">SKU</th><th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Barcode</th><th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Vendor</th></tr></thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700/60">
                            @forelse ($products as $product)
                                <tr class="bg-white dark:bg-gray-800"><td class="px-5 py-4 font-medium text-gray-800 dark:text-gray-100">#{{ $product->id }}</td><td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $product->product_name }}</td><td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $product->sku ?: '—' }}</td><td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $product->barcode ?: '—' }}</td><td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $product->vendor?->vendor_name ?? '—' }}</td></tr>
                            @empty
                                <tr class="bg-white dark:bg-gray-800"><td colspan="5" class="px-5 py-8 text-center text-gray-500 dark:text-gray-400">No products found for this account.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
