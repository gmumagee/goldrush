<x-app-layout title="Warehouse Details">
    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-4xl space-y-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 md:text-3xl">{{ $warehouse->warehouse_name }}</h1>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Warehouse #{{ $warehouse->id }}</p>
                </div>
                <div class="flex gap-3">
                    <a href="{{ route('warehouses.index') }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Back to Warehouses</a>
                    <a href="{{ route('warehouses.edit', $warehouse) }}" class="inline-flex items-center rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-violet-500">Edit Warehouse</a>
                </div>
            </div>
            @if (session('status'))
                <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-900/60 dark:bg-green-500/10 dark:text-green-300">{{ session('status') }}</div>
            @endif
            <x-validation-errors />
            <section class="panel">
                <div class="panel-body">
                    <dl class="grid gap-4 text-sm md:grid-cols-2">
                        <div><dt class="text-gray-500 dark:text-gray-400">Address</dt><dd class="mt-1 text-gray-800 dark:text-gray-100">{{ $warehouse->address ?: '—' }}</dd></div>
                        <div><dt class="text-gray-500 dark:text-gray-400">City</dt><dd class="mt-1 text-gray-800 dark:text-gray-100">{{ $warehouse->city ?: '—' }}</dd></div>
                        <div><dt class="text-gray-500 dark:text-gray-400">State</dt><dd class="mt-1 text-gray-800 dark:text-gray-100">{{ $warehouse->state ?: '—' }}</dd></div>
                        <div><dt class="text-gray-500 dark:text-gray-400">Zip Code</dt><dd class="mt-1 text-gray-800 dark:text-gray-100">{{ $warehouse->zip_code ?: '—' }}</dd></div>
                    </dl>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
