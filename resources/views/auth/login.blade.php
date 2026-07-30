<x-authentication-layout title="Login">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-100">Welcome back</h1>
        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Sign in to manage your vending operations.</p>
    </div>

    @if (\App\Support\Demo::isEnabled())
        <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="font-semibold">Public demo available</div>
                    <div class="mt-1">Enter the shared demo environment without creating an account. Demo data resets nightly.</div>
                </div>
                <a href="{{ route('demo.login') }}" class="inline-flex items-center justify-center rounded-xl bg-amber-500 px-4 py-2.5 text-sm font-semibold text-amber-950 transition hover:bg-amber-400">
                    Enter Demo
                </a>
            </div>
        </div>
    @endif

    @if (session('status'))
        <div class="mb-6 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-900/60 dark:bg-green-500/10 dark:text-green-300">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <div>
            <x-label for="email" value="Email" />
            <x-input id="email" name="email" type="email" :value="old('email')" required autofocus autocomplete="email" />
        </div>

        <div>
            <x-label for="password" value="Password" />
            <x-input id="password" name="password" type="password" required autocomplete="current-password" />
        </div>

        <label class="flex items-center gap-3 text-sm text-gray-600 dark:text-gray-300">
            <input id="remember" name="remember" type="checkbox" value="1" class="rounded border-gray-300 text-violet-500 shadow-sm focus:ring-violet-500">
            <span>Remember me</span>
        </label>

        <div class="flex items-center justify-between gap-4 pt-2">
            <div class="text-sm text-gray-500 dark:text-gray-400">
                @if (! \App\Support\Demo::isEnabled() && config('security.allow_self_registration', false) && \App\Support\Tenancy::isMulti())
                    Need an account?
                    <a href="{{ route('register') }}" class="font-medium text-violet-600 hover:text-violet-500 dark:text-violet-400 dark:hover:text-violet-300">Register</a>
                @endif
            </div>

            <x-button>Sign in</x-button>
        </div>
    </form>

    <x-validation-errors class="mt-6" />
</x-authentication-layout>
