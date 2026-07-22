<x-app-layout title="Products">
    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-7xl space-y-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 md:text-3xl">Products</h1>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Manage products for the selected account.</p>
                </div>

                @can('create', \App\Models\Product::class)
                    <a href="{{ route('products.create') }}" class="inline-flex items-center rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-violet-500">Add Product</a>
                @endcan
            </div>

            @if (session('status'))
                <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-900/60 dark:bg-green-500/10 dark:text-green-300">{{ session('status') }}</div>
            @endif

            <x-validation-errors />

            <section class="panel">
                <div class="panel-body border-b border-gray-200 dark:border-gray-700/60">
                    <form method="GET" action="{{ route('products.index') }}" class="grid gap-4 md:grid-cols-[1fr_auto]">
                        <x-input name="search" type="text" :value="$search" placeholder="Search SKU, product, brand, category, or barcode" />

                        <div class="flex gap-3">
                            <x-button>Search</x-button>
                            <a href="{{ route('products.index') }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Reset</a>
                        </div>
                    </form>
                </div>

                @if ($productsByCategory->isEmpty())
                    <div class="panel-body">
                        <div class="rounded-xl border border-dashed border-gray-200 bg-gray-50 px-5 py-8 text-center text-sm text-gray-500 dark:border-gray-700/60 dark:bg-gray-900/30 dark:text-gray-400">
                            No products found for this account.
                        </div>
                    </div>
                @else
                    <div class="panel-body space-y-4">
                        <div x-data class="flex flex-wrap items-center justify-end gap-3">
                            <button
                                type="button"
                                class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                                @click="$dispatch('products-expand-all')"
                            >
                                Expand all
                            </button>

                            <button
                                type="button"
                                class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                                @click="$dispatch('products-collapse-all')"
                            >
                                Collapse all
                            </button>
                        </div>

                        @foreach ($productsByCategory as $category => $products)
                            @php($accordionId = 'product-category-panel-'.$loop->index)
                            <section
                                class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700/60 dark:bg-gray-800"
                                x-data="{ open: {{ $search !== '' ? 'true' : 'false' }} }"
                                x-on:products-expand-all.window="open = true"
                                x-on:products-collapse-all.window="open = false"
                            >
                                <button
                                    type="button"
                                    class="flex w-full items-center justify-between gap-4 px-5 py-4 text-left transition hover:bg-gray-50 dark:hover:bg-gray-700/30"
                                    @click="open = ! open"
                                    :aria-expanded="open.toString()"
                                    aria-controls="{{ $accordionId }}"
                                >
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-3">
                                            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">{{ $category }}</h2>
                                            <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600 dark:bg-gray-700/60 dark:text-gray-300">
                                                {{ $products->count() }} {{ \Illuminate\Support\Str::plural('product', $products->count()) }}
                                            </span>
                                        </div>
                                    </div>

                                    <svg
                                        class="h-5 w-5 shrink-0 text-gray-400 transition-transform duration-200"
                                        :class="{ 'rotate-180': open }"
                                        viewBox="0 0 20 20"
                                        fill="currentColor"
                                        aria-hidden="true"
                                    >
                                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.938a.75.75 0 1 1 1.08 1.04l-4.25 4.51a.75.75 0 0 1-1.08 0l-4.25-4.51a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
                                    </svg>
                                </button>

                                <div id="{{ $accordionId }}" x-cloak x-show="open" x-transition.opacity.duration.150ms class="border-t border-gray-200 dark:border-gray-700/60">
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700/60">
                                            <thead class="bg-gray-50 dark:bg-gray-800/80">
                                                <tr>
                                                    <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">SKU</th>
                                                    <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Product Name</th>
                                                    <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Brand</th>
                                                    <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Size</th>
                                                    <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Package Type</th>
                                                    <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Vendor</th>
                                                    <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Barcode</th>
                                                    <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700/60">
                                                @foreach ($products as $product)
                                                    <tr class="bg-white dark:bg-gray-800">
                                                        <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $product->sku ?: '—' }}</td>
                                                        <td class="px-5 py-4 font-medium text-gray-800 dark:text-gray-100">{{ $product->product_name }}</td>
                                                        <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $product->brand ?: '—' }}</td>
                                                        <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $product->size ?: '—' }}</td>
                                                        <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $product->package_type ?: '—' }}</td>
                                                        <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $product->vendor?->vendor_name ?? '—' }}</td>
                                                        <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $product->barcode ?: '—' }}</td>
                                                        <td class="px-5 py-4">
                                                            <div class="flex flex-wrap gap-2">
                                                                <a href="{{ route('products.show', $product) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">View</a>

                                                                @can('update', $product)
                                                                    <a href="{{ route('products.edit', $product) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Edit</a>
                                                                @endcan

                                                                @can('delete', $product)
                                                                    <form method="POST" action="{{ route('products.destroy', $product) }}">
                                                                        @csrf
                                                                        @method('DELETE')
                                                                        <button type="submit" class="inline-flex items-center rounded-xl border border-red-300 px-3 py-1.5 text-xs font-medium text-red-700 transition hover:bg-red-50 dark:border-red-500/40 dark:text-red-300 dark:hover:bg-red-500/10">Delete</button>
                                                                    </form>
                                                                @endcan
                                                            </div>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </section>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
