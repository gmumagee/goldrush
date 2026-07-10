<x-authentication-layout title="Select account">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-100">Select account</h1>
        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Choose the workspace you want to manage in this session.</p>
    </div>

    <form method="POST" action="{{ route('accounts.select') }}" class="space-y-4">
        @csrf

        @foreach ($accounts as $account)
            <label class="flex cursor-pointer items-start gap-4 rounded-2xl border border-gray-200 bg-white px-4 py-4 shadow-sm transition hover:border-violet-300 hover:bg-violet-50/40 dark:border-gray-700/60 dark:bg-gray-800 dark:hover:border-violet-500/50 dark:hover:bg-violet-500/10">
                <input
                    name="account_id"
                    type="radio"
                    value="{{ $account->id }}"
                    required
                    class="mt-1 rounded border-gray-300 text-violet-500 focus:ring-violet-500"
                >
                <span class="min-w-0">
                    <span class="block text-sm font-semibold text-gray-800 dark:text-gray-100">{{ $account->account_name }}</span>
                    <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">Account ID: {{ $account->id }}</span>
                </span>
            </label>
        @endforeach

        <div class="flex items-center justify-end gap-4 pt-3">
            <x-button>Continue</x-button>
        </div>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-4">
        @csrf
        <button
            type="submit"
            class="inline-flex items-center justify-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
        >
            Logout
        </button>
    </form>

    <x-validation-errors class="mt-6" />
</x-authentication-layout>
