<x-app-layout title="Edit Account User">
    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-3xl space-y-6">
            @php
                $currentRoleValue = collect($roleOptions)->first(
                    fn ($role) => strtolower(trim((string) $role->value)) === strtolower(trim((string) old('role', $membership->role)))
                )?->value ?? old('role', $membership->role);
            @endphp

            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 md:text-3xl">Edit Account User</h1>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Update this user’s role and membership status for the current account.</p>
                </div>
                <a href="{{ route('account-users.index') }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Back to Users</a>
            </div>

            <section class="panel">
                <div class="panel-body space-y-6">
                    <dl class="grid gap-4 rounded-2xl border border-gray-200 p-5 text-sm dark:border-gray-700/60 md:grid-cols-3">
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Name</dt>
                            <dd class="mt-1 font-medium text-gray-800 dark:text-gray-100">{{ $membership->user?->name ?? 'Unknown User' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Email</dt>
                            <dd class="mt-1 font-medium text-gray-800 dark:text-gray-100">{{ $membership->user?->email ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">User Status</dt>
                            <dd class="mt-1 font-medium text-gray-800 dark:text-gray-100">{{ $userStatusLabels[strtolower(trim((string) $membership->user?->status))] ?? ($membership->user?->status ?: 'Unknown') }}</dd>
                        </div>
                    </dl>

                    <form method="POST" action="{{ route('account-users.update', $membership) }}" class="space-y-5">
                        @csrf
                        @method('PUT')

                        <div class="grid gap-5 md:grid-cols-2">
                            <div>
                                <x-label for="role" value="Role" />
                                <select id="role" name="role" class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100" required>
                                    @foreach ($roleOptions as $role)
                                        <option value="{{ $role->value }}" @selected($currentRoleValue === $role->value)>{{ $role->displayLabel() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <x-label for="status" value="Membership Status" />
                                <select id="status" name="status" class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100" required>
                                    @foreach ($membershipStatusOptions as $status)
                                        <option value="{{ $status->value }}" @selected(old('status', $membership->status) === $status->value)>{{ $status->displayLabel() }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-3">
                            <a href="{{ route('account-users.index') }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Cancel</a>
                            <x-button>Save User Access</x-button>
                        </div>
                    </form>

                    <x-validation-errors />
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
