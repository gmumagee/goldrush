<header class="sticky top-0 z-30 before:absolute before:inset-0 before:-z-10 before:bg-gray-100/90 before:backdrop-blur-md dark:before:bg-gray-900/90">
    <div class="px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 items-center justify-between border-b border-gray-200 dark:border-gray-700/60">
            <div class="flex items-center gap-3">
                <button
                    class="text-gray-500 hover:text-gray-600 dark:hover:text-gray-400 lg:hidden"
                    @click.stop="sidebarOpen = !sidebarOpen"
                    aria-controls="sidebar"
                    :aria-expanded="sidebarOpen"
                >
                    <span class="sr-only">Open sidebar</span>
                    <svg class="h-6 w-6 fill-current" viewBox="0 0 24 24">
                        <rect x="4" y="5" width="16" height="2" />
                        <rect x="4" y="11" width="16" height="2" />
                        <rect x="4" y="17" width="16" height="2" />
                    </svg>
                </button>

                <div>
                    <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ auth()->user()?->name ?? 'User' }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ session('current_account_id') ? 'Account '.session('current_account_id') : 'Workspace navigation' }}</p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <x-theme-toggle />

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button
                        type="submit"
                        class="inline-flex items-center rounded-xl border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                    >
                        Logout
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>
