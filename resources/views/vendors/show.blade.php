<x-app-layout title="Vendor Details">
    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-5xl space-y-6">
            <div class="flex items-center justify-between gap-4">
                <div><h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 md:text-3xl">{{ $vendor->vendor_name }}</h1><p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ $vendor->location ?: 'No location set' }}</p></div>
                <div class="flex gap-3"><a href="{{ route('vendors.index') }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Back to Vendors</a>@can('update', $vendor)<a href="{{ route('vendors.edit', $vendor) }}" class="inline-flex items-center rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-violet-500">Edit Vendor</a>@endcan</div>
            </div>
            @if (session('status'))
                <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-900/60 dark:bg-green-500/10 dark:text-green-300">{{ session('status') }}</div>
            @endif
            <x-validation-errors />
            <section class="panel"><div class="panel-header"><div><h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Products</h2></div></div><div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700/60"><thead class="bg-gray-50 dark:bg-gray-800/80"><tr><th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">SKU</th><th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Product Name</th><th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Category</th></tr></thead><tbody class="divide-y divide-gray-200 dark:divide-gray-700/60">@forelse ($vendor->products as $product)<tr class="bg-white dark:bg-gray-800"><td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $product->sku ?: '—' }}</td><td class="px-5 py-4 font-medium text-gray-800 dark:text-gray-100">{{ $product->product_name }}</td><td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $product->category ?: '—' }}</td></tr>@empty<tr class="bg-white dark:bg-gray-800"><td colspan="3" class="px-5 py-8 text-center text-gray-500 dark:text-gray-400">No products are assigned to this vendor.</td></tr>@endforelse</tbody></table></div></section>
        </div>
    </div>
</x-app-layout>
