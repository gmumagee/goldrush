<x-app-layout title="Dashboard">
    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-7xl">
            <div>
                <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 md:text-3xl">Dashboard</h1>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    {{ $account?->account_name ?? 'Selected account' }} · Account ID {{ session('current_account_id') }}
                </p>
            </div>
        </div>
    </div>
</x-app-layout>
