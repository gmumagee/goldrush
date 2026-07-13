<x-app-layout title="Add Account User">
    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-3xl space-y-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 md:text-3xl">Add Account User</h1>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Create a new login or attach an existing user to the current account.</p>
                </div>
                <a href="{{ route('account-users.index') }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Back to Users</a>
            </div>

            <section class="panel">
                <div class="panel-body">
                    <div class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-700 dark:border-blue-900/60 dark:bg-blue-500/10 dark:text-blue-300">
                        If the email already belongs to an existing user, that user will be added to this account and their existing password will not be changed.
                    </div>

                    <form method="POST" action="{{ route('account-users.store') }}" class="mt-6 space-y-5">
                        @csrf
                        <div class="grid gap-5 md:grid-cols-2">
                            <div>
                                <x-label for="name" value="Name" />
                                <x-input id="name" name="name" type="text" :value="old('name')" required />
                            </div>
                            <div>
                                <x-label for="email" value="Email" />
                                <x-input id="email" name="email" type="email" :value="old('email')" required />
                            </div>
                        </div>

                        <div class="grid gap-5 md:grid-cols-3">
                            <div>
                                <x-label for="role" value="Role" />
                                <select id="role" name="role" class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100" required>
                                    @foreach ($roleOptions as $role)
                                        <option value="{{ $role->value }}" @selected(old('role', \App\Models\AccountUser::ROLE_TECHNICIAN) === $role->value)>{{ $role->displayLabel() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <x-label for="status" value="Membership Status" />
                                <select id="status" name="status" class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100" required>
                                    @foreach ($membershipStatusOptions as $status)
                                        <option value="{{ $status->value }}" @selected(old('status', \App\Models\AccountUser::STATUS_ACTIVE) === $status->value)>{{ $status->displayLabel() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <x-label for="user_status" value="User Status" />
                                <select id="user_status" name="user_status" class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100" required>
                                    @foreach ($userStatusOptions as $status)
                                        <option value="{{ $status->value }}" @selected(old('user_status', \App\Models\User::STATUS_ACTIVE) === $status->value)>{{ $status->displayLabel() }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="grid gap-5 md:grid-cols-2">
                            <div>
                                <x-label for="password" value="Temporary Password" />
                                <x-input id="password" name="password" type="password" />
                            </div>
                            <div>
                                <x-label for="password_confirmation" value="Confirm Temporary Password" />
                                <x-input id="password_confirmation" name="password_confirmation" type="password" />
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-3">
                            <a href="{{ route('account-users.index') }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Cancel</a>
                            <x-button>Add User</x-button>
                        </div>
                    </form>

                    <x-validation-errors class="mt-6" />
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
