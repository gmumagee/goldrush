<x-app-layout title="Admin Accounts">
    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-7xl space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 md:text-3xl">Platform Accounts</h1>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Cross-account directory for platform operators.</p>
            </div>

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
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700/60">
                                @foreach ($accounts as $account)
                                    <tr class="bg-white dark:bg-gray-800">
                                        <td class="px-5 py-4 font-medium text-gray-800 dark:text-gray-100">#{{ $account->id }}</td>
                                        <td class="px-5 py-4 text-gray-600 dark:text-gray-300">
                                            <div>{{ $account->account_name }}</div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $account->slug }}</div>
                                        </td>
                                        <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ ucfirst((string) $account->status) }}</td>
                                        <td class="px-5 py-4 text-gray-600 dark:text-gray-300">
                                            {{ $account->created_at ? \App\Support\AppDateTime::displayDate($account->created_at) : '—' }}
                                        </td>
                                        <td class="px-5 py-4 text-right tabular-nums text-gray-600 dark:text-gray-300">{{ $account->member_count }}</td>
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
