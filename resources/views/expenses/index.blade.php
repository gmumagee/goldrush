<x-app-layout title="Expenses">
    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-7xl space-y-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 md:text-3xl">Expenses</h1>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Track one-time business expenses for locations or the account as a whole.</p>
                </div>
                @can('create', \App\Models\Expense::class)
                    <a href="{{ route('expenses.create') }}" class="inline-flex items-center rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-violet-500">Add Expense</a>
                @endcan
            </div>

            @if (session('status'))
                <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-900/60 dark:bg-green-500/10 dark:text-green-300">{{ session('status') }}</div>
            @endif

            <x-validation-errors />

            <section class="panel">
                <div class="panel-body border-b border-gray-200 dark:border-gray-700/60">
                    <form method="GET" action="{{ route('expenses.index') }}" class="space-y-4">
                        <div class="grid gap-4 md:grid-cols-2">
                            <select name="location_filter" class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100">
                                <option value="">All locations</option>
                                <option value="general" @selected($filters['location_filter'] === 'general')>General only</option>
                                @foreach ($locations as $location)
                                    <option value="{{ $location->id }}" @selected((string) $filters['location_filter'] === (string) $location->id)>{{ $location->location_name }}</option>
                                @endforeach
                            </select>

                            <select name="category" class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100">
                                <option value="">All categories</option>
                                @foreach ($categoryOptions as $value => $label)
                                    <option value="{{ $value }}" @selected($filters['category'] === $value)>{{ $label }}</option>
                                @endforeach
                            </select>

                            <x-input name="date_from" type="date" :value="$filters['date_from']" />
                            <x-input name="date_to" type="date" :value="$filters['date_to']" />
                        </div>

                        <div class="flex gap-3">
                            <x-button>Filter</x-button>
                            <a href="{{ route('expenses.index') }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Reset</a>
                        </div>
                    </form>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700/60">
                        <thead class="bg-gray-50 dark:bg-gray-800/80">
                            <tr>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Expense Date</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Category</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Location</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Vendor</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Description</th>
                                <th class="px-5 py-3 text-right font-medium text-gray-500 dark:text-gray-400">Amount</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700/60">
                            @forelse ($expenses as $expense)
                                <tr class="bg-white dark:bg-gray-800">
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ \App\Support\AppDateTime::displayDate($expense->expense_date) }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $expense->categoryLabel() }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">
                                        @if ($expense->isGeneral())
                                            <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700 dark:bg-gray-700/60 dark:text-gray-200">General</span>
                                        @else
                                            {{ $expense->location?->location_name ?? 'General' }}
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $expense->vendor ?: '—' }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $expense->description ?: '—' }}</td>
                                    <td class="px-5 py-4 text-right tabular-nums text-gray-600 dark:text-gray-300">{{ number_format((float) $expense->amount, 2) }}</td>
                                    <td class="px-5 py-4">
                                        <div class="flex flex-wrap gap-2">
                                            @can('update', $expense)
                                                <a href="{{ route('expenses.edit', $expense) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Edit</a>
                                            @endcan
                                            @can('delete', $expense)
                                                <form method="POST" action="{{ route('expenses.destroy', $expense) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="inline-flex items-center rounded-xl border border-red-300 px-3 py-1.5 text-xs font-medium text-red-700 transition hover:bg-red-50 dark:border-red-500/40 dark:text-red-300 dark:hover:bg-red-500/10">Delete</button>
                                                </form>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr class="bg-white dark:bg-gray-800">
                                    <td colspan="7" class="px-5 py-8 text-center text-gray-500 dark:text-gray-400">No expenses found for this account.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="panel-body">{{ $expenses->links() }}</div>
            </section>
        </div>
    </div>
</x-app-layout>
