<x-authentication-layout title="Confirm Password">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-100">Confirm your password</h1>
        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Confirm your password before continuing with this protected action.</p>
    </div>

    <form method="POST" action="{{ route('password.confirm.store') }}" class="space-y-5">
        @csrf

        <div>
            <x-label for="password" value="Password" />
            <x-input id="password" name="password" type="password" required autofocus autocomplete="current-password" />
        </div>

        <div class="flex items-center justify-end gap-3 pt-2">
            <a href="{{ route('dashboard') }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Cancel</a>
            <x-button>Confirm Password</x-button>
        </div>
    </form>

    <x-validation-errors class="mt-6" />
</x-authentication-layout>
