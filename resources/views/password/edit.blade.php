<x-app-layout title="Change Password">
    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-3xl space-y-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 md:text-3xl">Change Password</h1>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Update your password for the current login. Your existing password is never shown.</p>
                </div>
                <a href="{{ route('dashboard') }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Back to Dashboard</a>
            </div>

            @if (session('status'))
                <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-900/60 dark:bg-green-500/10 dark:text-green-300">{{ session('status') }}</div>
            @endif

            <section class="panel">
                <div class="panel-body space-y-6">
                    <form method="POST" action="{{ route('password.update') }}" class="space-y-5">
                        @csrf
                        @method('PUT')

                        <div>
                            <x-label for="current_password" value="Current Password" />
                            <x-input id="current_password" name="current_password" type="password" autocomplete="current-password" required />
                        </div>

                        <div class="grid gap-5 md:grid-cols-2">
                            <div>
                                <x-label for="password" value="New Password" />
                                <x-input id="password" name="password" type="password" autocomplete="new-password" required />
                            </div>
                            <div>
                                <x-label for="password_confirmation" value="Confirm New Password" />
                                <x-input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required />
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-3">
                            <a href="{{ route('dashboard') }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Cancel</a>
                            <x-button>Update Password</x-button>
                        </div>
                    </form>

                    <x-validation-errors />
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
