<x-authentication-layout title="Register">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-100">Create account</h1>
        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Set up your business workspace and start tracking inventory.</p>
    </div>

    <form method="POST" action="{{ route('register') }}" class="space-y-5">
        @csrf

        <div>
            <x-label for="name" value="Name" />
            <x-input id="name" name="name" type="text" :value="old('name')" required autofocus autocomplete="name" />
        </div>

        <div>
            <x-label for="email" value="Email" />
            <x-input id="email" name="email" type="email" :value="old('email')" required autocomplete="email" />
        </div>

        @if (\App\Support\Tenancy::isMulti())
            <div>
                <x-label for="account_name" value="Business / account name" />
                <x-input id="account_name" name="account_name" type="text" :value="old('account_name')" required autocomplete="organization" />
            </div>
        @endif

        <div>
            <x-label for="password" value="Password" />
            <x-input id="password" name="password" type="password" required autocomplete="new-password" />
        </div>

        <div>
            <x-label for="password_confirmation" value="Confirm password" />
            <x-input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" />
        </div>

        <div class="flex items-center justify-between gap-4 pt-2">
            <div class="text-sm text-gray-500 dark:text-gray-400">
                Already registered?
                <a href="{{ route('login') }}" class="font-medium text-violet-600 hover:text-violet-500 dark:text-violet-400 dark:hover:text-violet-300">Sign in</a>
            </div>

            <x-button>Register</x-button>
        </div>
    </form>

    <x-validation-errors class="mt-6" />
</x-authentication-layout>
