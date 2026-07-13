<x-app-layout title="Account Users">
    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-7xl space-y-6">
            @php
                $membershipBadge = static function (?string $status): string {
                    return strtolower(trim((string) $status)) === 'active'
                        ? 'bg-green-100 text-green-800 dark:bg-green-500/15 dark:text-green-300'
                        : 'bg-gray-100 text-gray-700 dark:bg-gray-700/60 dark:text-gray-200';
                };
                $userBadge = static function (?string $status): string {
                    return strtolower(trim((string) $status)) === 'active'
                        ? 'bg-blue-100 text-blue-800 dark:bg-blue-500/15 dark:text-blue-300'
                        : 'bg-gray-100 text-gray-700 dark:bg-gray-700/60 dark:text-gray-200';
                };
            @endphp

            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 md:text-3xl">Account Users</h1>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Manage user access, roles, and membership status for the current account.</p>
                </div>
                <a href="{{ route('account-users.create') }}" class="inline-flex items-center rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-violet-500">Add User</a>
            </div>

            @if (session('status'))
                <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-900/60 dark:bg-green-500/10 dark:text-green-300">{{ session('status') }}</div>
            @endif

            <x-validation-errors />

            <section class="panel">
                <div class="panel-body border-b border-gray-200 dark:border-gray-700/60">
                    <form method="GET" action="{{ route('account-users.index') }}" class="grid gap-4 md:grid-cols-[1fr_auto]">
                        <x-input name="search" type="text" :value="$search" placeholder="Search name or email" />
                        <div class="flex gap-3">
                            <x-button>Search</x-button>
                            <a href="{{ route('account-users.index') }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Reset</a>
                        </div>
                    </form>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700/60">
                        <thead class="bg-gray-50 dark:bg-gray-800/80">
                            <tr>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Name</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Email</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Role</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Membership Status</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">User Status</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700/60">
                            @forelse ($memberships as $membership)
                                <tr class="bg-white dark:bg-gray-800">
                                    <td class="px-5 py-4 font-medium text-gray-800 dark:text-gray-100">{{ $membership->user?->name ?? 'Unknown User' }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $membership->user?->email ?? '—' }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $roleLabels[strtolower(trim((string) $membership->role))] ?? $membership->role }}</td>
                                    <td class="px-5 py-4"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $membershipBadge($membership->status) }}">{{ $membershipStatusLabels[strtolower(trim((string) $membership->status))] ?? $membership->status }}</span></td>
                                    <td class="px-5 py-4"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $userBadge($membership->user?->status) }}">{{ $userStatusLabels[strtolower(trim((string) $membership->user?->status))] ?? ($membership->user?->status ?: 'Unknown') }}</span></td>
                                    <td class="px-5 py-4">
                                        <div class="flex flex-wrap gap-2">
                                            <a href="{{ route('account-users.edit', $membership) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Edit</a>
                                            @if (strtolower(trim((string) $membership->status)) === 'active')
                                                <form method="POST" action="{{ route('account-users.deactivate', $membership) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="inline-flex items-center rounded-xl border border-amber-300 px-3 py-1.5 text-xs font-medium text-amber-700 transition hover:bg-amber-50 dark:border-amber-500/40 dark:text-amber-300 dark:hover:bg-amber-500/10">Deactivate</button>
                                                </form>
                                            @endif
                                            <form method="POST" action="{{ route('account-users.destroy', $membership) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="inline-flex items-center rounded-xl border border-red-300 px-3 py-1.5 text-xs font-medium text-red-700 transition hover:bg-red-50 dark:border-red-500/40 dark:text-red-300 dark:hover:bg-red-500/10">Remove</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr class="bg-white dark:bg-gray-800">
                                    <td colspan="6" class="px-5 py-8 text-center text-gray-500 dark:text-gray-400">No account users found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="panel-body">{{ $memberships->links() }}</div>
            </section>
        </div>
    </div>
</x-app-layout>
