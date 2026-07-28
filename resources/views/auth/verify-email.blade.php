<x-authentication-layout title="Verify Email">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-100">Verify your email</h1>
        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Check your inbox for a verification link before accessing the application.</p>
    </div>

    @if (session('status'))
        <div class="mb-6 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-900/60 dark:bg-green-500/10 dark:text-green-300">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('verification.send') }}" class="space-y-5">
        @csrf

        <p class="text-sm text-gray-600 dark:text-gray-300">
            No email yet? Request another verification link.
        </p>

        <div class="flex items-center justify-between gap-4 pt-2">
            <a href="{{ route('logout') }}"
               onclick="event.preventDefault(); document.getElementById('verify-email-logout-form').submit();"
               class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                Sign out
            </a>

            <x-button>Resend Verification</x-button>
        </div>
    </form>

    <form id="verify-email-logout-form" method="POST" action="{{ route('logout') }}" class="hidden">
        @csrf
    </form>

    <x-validation-errors class="mt-6" />
</x-authentication-layout>
