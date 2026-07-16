<x-app-layout title="Reset User Password">
    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-3xl space-y-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 md:text-3xl">Reset User Password</h1>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Set a new password for this account user. Existing passwords are never displayed.</p>
                </div>
                <a href="{{ route('account-users.index') }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Back to Users</a>
            </div>

            <section class="panel">
                <div class="panel-body space-y-6">
                    <dl class="grid gap-4 rounded-2xl border border-gray-200 p-5 text-sm dark:border-gray-700/60 md:grid-cols-2">
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Reset Password For</dt>
                            <dd class="mt-1 font-medium text-gray-800 dark:text-gray-100">{{ $membership->user?->name ?? 'Unknown User' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Email</dt>
                            <dd class="mt-1 font-medium text-gray-800 dark:text-gray-100">{{ $membership->user?->email ?? '—' }}</dd>
                        </div>
                    </dl>

                    <form method="POST" action="{{ route('account-users.password.update', $membership) }}" class="space-y-5">
                        @csrf
                        @method('PUT')

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
                            <a href="{{ route('account-users.index') }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Cancel</a>
                            <x-button>Reset Password</x-button>
                        </div>
                    </form>

                    <x-validation-errors />
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
