<x-app-layout title="Admin Accounts">
    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-7xl space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 md:text-3xl">Platform Accounts</h1>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Cross-account directory for platform operators.</p>
            </div>

            @if (session('status'))
                <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-900/60 dark:bg-green-500/10 dark:text-green-300">{{ session('status') }}</div>
            @endif

            <x-validation-errors />

            <section class="panel">
                @if ($accounts->isEmpty())
                    <div class="panel-body">
                        <div class="rounded-xl border border-dashed border-gray-200 bg-gray-50 px-5 py-8 text-center text-sm text-gray-500 dark:border-gray-700/60 dark:bg-gray-900/30 dark:text-gray-400">
                            No accounts found.
                        </div>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700/60">
                            <thead class="bg-gray-50 dark:bg-gray-800/80">
                                <tr>
                                    <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">ID</th>
                                    <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Account</th>
                                    <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Status</th>
                                    <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Created</th>
                                    <th class="px-5 py-3 text-right font-medium text-gray-500 dark:text-gray-400">Members</th>
                                    <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700/60">
                                @foreach ($accounts as $account)
                                    @php
                                        $recentBackups = ($backupsByAccount[$account->id] ?? collect())->take(5);
                                    @endphp
                                    <tr class="bg-white dark:bg-gray-800">
                                        <td class="px-5 py-4 font-medium text-gray-800 dark:text-gray-100">#{{ $account->id }}</td>
                                        <td class="px-5 py-4 text-gray-600 dark:text-gray-300">
                                            <div>{{ $account->account_name }}</div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $account->slug }}</div>
                                        </td>
                                        <td class="px-5 py-4 text-gray-600 dark:text-gray-300">
                                            @if ($account->status === \App\Models\Account::STATUS_ACTIVE)
                                                <span class="inline-flex rounded-full bg-green-100 px-2.5 py-1 text-xs font-medium text-green-800 dark:bg-green-500/15 dark:text-green-300">Active</span>
                                            @else
                                                <span class="inline-flex rounded-full bg-red-100 px-2.5 py-1 text-xs font-medium text-red-800 dark:bg-red-500/15 dark:text-red-300">Blocked</span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-4 text-gray-600 dark:text-gray-300">
                                            {{ $account->created_at ? \App\Support\AppDateTime::displayDate($account->created_at) : '—' }}
                                        </td>
                                        <td class="px-5 py-4 text-right tabular-nums text-gray-600 dark:text-gray-300">{{ $account->member_count }}</td>
                                        <td class="px-5 py-4">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <form method="POST" action="{{ route('admin.accounts.backups.store', $account) }}">
                                                    @csrf
                                                    <button type="submit" class="inline-flex items-center rounded-xl border border-blue-300 px-3 py-1.5 text-xs font-medium text-blue-700 transition hover:bg-blue-50 dark:border-blue-500/40 dark:text-blue-300 dark:hover:bg-blue-500/10">Backup</button>
                                                </form>
                                                @if ($account->status === \App\Models\Account::STATUS_ACTIVE)
                                                    <form method="POST" action="{{ route('admin.accounts.block', $account) }}" onsubmit="return confirm('Are you sure? This will suspend all users of {{ addslashes($account->account_name) }}.');">
                                                        @csrf
                                                        <button type="submit" class="inline-flex items-center rounded-xl border border-red-300 px-3 py-1.5 text-xs font-medium text-red-700 transition hover:bg-red-50 dark:border-red-500/40 dark:text-red-300 dark:hover:bg-red-500/10">Block</button>
                                                    </form>
                                                @else
                                                    <form method="POST" action="{{ route('admin.accounts.unblock', $account) }}">
                                                        @csrf
                                                        <button type="submit" class="inline-flex items-center rounded-xl border border-green-300 px-3 py-1.5 text-xs font-medium text-green-700 transition hover:bg-green-50 dark:border-green-500/40 dark:text-green-300 dark:hover:bg-green-500/10">Unblock</button>
                                                    </form>
                                                @endif
                                            </div>

                                            <div class="mt-3 space-y-2">
                                                @forelse ($recentBackups as $backup)
                                                    <div class="rounded-xl border border-gray-200 px-3 py-2 text-xs text-gray-600 dark:border-gray-700/60 dark:text-gray-300">
                                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                                            <span>{{ \App\Support\AppDateTime::displayDateTime($backup->created_at) }}</span>
                                                            @if ($backup->status === \App\Models\AccountBackup::STATUS_READY)
                                                                <span class="inline-flex rounded-full bg-green-100 px-2 py-0.5 font-medium text-green-800 dark:bg-green-500/15 dark:text-green-300">Ready</span>
                                                            @elseif ($backup->status === \App\Models\AccountBackup::STATUS_FAILED)
                                                                <span class="inline-flex rounded-full bg-red-100 px-2 py-0.5 font-medium text-red-800 dark:bg-red-500/15 dark:text-red-300">Failed</span>
                                                            @else
                                                                <span class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 font-medium text-amber-800 dark:bg-amber-500/15 dark:text-amber-300">Pending</span>
                                                            @endif
                                                        </div>
                                                        <div class="mt-1 text-gray-500 dark:text-gray-400">
                                                            Requested by {{ $backup->requestedBy?->name ?? 'Unknown user' }}
                                                            @if ($backup->file_size_bytes)
                                                                · {{ number_format($backup->file_size_bytes) }} bytes
                                                            @endif
                                                        </div>
                                                        @if ($backup->status === \App\Models\AccountBackup::STATUS_READY)
                                                            <div class="mt-2">
                                                                <a href="{{ route('admin.account-backups.download', $backup) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Download ZIP</a>
                                                            </div>
                                                        @elseif ($backup->status === \App\Models\AccountBackup::STATUS_FAILED && $backup->failure_message)
                                                            <div class="mt-2 text-red-600 dark:text-red-300">{{ $backup->failure_message }}</div>
                                                        @endif
                                                    </div>
                                                @empty
                                                    <div class="text-xs text-gray-500 dark:text-gray-400">No backups generated yet.</div>
                                                @endforelse
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="panel-body">{{ $accounts->links() }}</div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
