@php
    $currentAccount = session('current_account_id')
        ? \App\Models\Account::query()->select(['id', 'account_name', 'slug'])->find(session('current_account_id'))
        : null;

    $operationsOpen = request()->routeIs('services.*') || request()->routeIs('transactions.*');
    $inventoryOpen = request()->routeIs('products.*') || request()->routeIs('vendors.*') || request()->routeIs('warehouses.*') || request()->routeIs('purchases.*');
    $routeManagementOpen = request()->routeIs('routes.*') || request()->routeIs('routes.locations.*') || request()->routeIs('locations.*') || request()->routeIs('machines.*') || request()->routeIs('bins.*');
    $accountOpen = request()->routeIs('accounts.*') || request()->routeIs('account-users.*') || request()->is('users*') || request()->is('settings*');

    $sectionButtonClasses = 'flex w-full min-h-11 items-center justify-between rounded-xl px-3 py-2.5 text-left text-sm font-medium transition';
    $sectionButtonStateClasses = 'text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700/60';
    $activeChildClasses = 'bg-violet-500/10 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300';
    $inactiveChildClasses = 'text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700/60';
@endphp

<div class="min-w-fit">
    <div
        class="fixed inset-0 z-40 bg-gray-900/30 transition-opacity duration-200 lg:hidden"
        :class="sidebarOpen ? 'opacity-100' : 'pointer-events-none opacity-0'"
        x-cloak
        aria-hidden="true"
    ></div>

    <aside
        id="sidebar"
        class="absolute left-0 top-0 z-40 flex h-[100dvh] w-64 shrink-0 -translate-x-64 flex-col overflow-y-auto bg-white p-4 transition-all duration-200 ease-in-out lg:static lg:translate-x-0 lg:overflow-y-auto lg:w-64 dark:bg-gray-800"
        :class="sidebarOpen ? 'translate-x-0' : '-translate-x-64 lg:translate-x-0'"
        @click.outside="sidebarOpen = false"
        @keydown.escape.window="sidebarOpen = false"
    >
        <div class="mb-10 flex justify-between pr-3 sm:px-2">
            <button class="text-gray-500 hover:text-gray-400 lg:hidden" @click.stop="sidebarOpen = false">
                <span class="sr-only">Close sidebar</span>
                <svg class="h-6 w-6 fill-current" viewBox="0 0 24 24">
                    <path d="M10.7 18.7l1.4-1.4L7.8 13H20v-2H7.8l4.3-4.3-1.4-1.4L4 12z" />
                </svg>
            </button>

            <a class="block" href="{{ route('dashboard') }}">
                <svg class="fill-violet-500" xmlns="http://www.w3.org/2000/svg" width="32" height="32">
                    <path d="M31.956 14.8C31.372 6.92 25.08.628 17.2.044V5.76a9.04 9.04 0 0 0 9.04 9.04h5.716ZM14.8 26.24v5.716C6.92 31.372.63 25.08.044 17.2H5.76a9.04 9.04 0 0 1 9.04 9.04Zm11.44-9.04h5.716c-.584 7.88-6.876 14.172-14.756 14.756V26.24a9.04 9.04 0 0 1 9.04-9.04ZM.044 14.8C.63 6.92 6.92.628 14.8.044V5.76a9.04 9.04 0 0 1-9.04 9.04H.044Z" />
                </svg>
            </a>
        </div>

        <div class="space-y-5">
            <div>
                <h3 class="pl-3 text-xs font-semibold uppercase text-gray-400 dark:text-gray-500">
                    <span class="hidden w-6 text-center lg:hidden" aria-hidden="true">•••</span>
                    <span class="block">Workspace</span>
                </h3>

                @if ($currentAccount?->slug)
                    <div class="mt-3 rounded-xl bg-gray-50 px-3 py-2 text-xs text-gray-500 dark:bg-gray-900/40 dark:text-gray-400">
                        <div class="font-medium text-gray-700 dark:text-gray-200">{{ $currentAccount->account_name }}</div>
                        <div class="mt-1 font-mono">{{ $currentAccount->slug }}</div>
                    </div>
                @endif

                <ul class="mt-3 space-y-1">
                    <li>
                        <a
                            href="{{ route('dashboard') }}"
                            class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition {{ request()->routeIs('dashboard') ? $activeChildClasses : $inactiveChildClasses }}"
                        >
                            <svg class="h-5 w-5 shrink-0 fill-current" viewBox="0 0 16 16" aria-hidden="true">
                                <path d="M5.936.278A7.983 7.983 0 0 1 8 0a8 8 0 1 1-8 8c0-.722.104-1.413.278-2.064a1 1 0 1 1 1.932.516A5.99 5.99 0 0 0 2 8a6 6 0 1 0 6-6c-.53 0-1.045.076-1.548.21A1 1 0 1 1 5.936.278Z" />
                                <path d="M6.068 7.482A2.003 2.003 0 0 0 8 10a2 2 0 1 0-.518-3.932L3.707 2.293a1 1 0 0 0-1.414 1.414l3.775 3.775Z" />
                            </svg>
                            <span class="opacity-100">Dashboard</span>
                        </a>
                    </li>
                </ul>
            </div>

            <div
                x-data="{
                    open: {{ $operationsOpen ? 'true' : 'false' }},
                    init() {
                        const saved = localStorage.getItem('sidebar-operations-open');
                        this.open = {{ $operationsOpen ? 'true' : 'false' }} || saved === 'true';
                        this.$watch('open', value => localStorage.setItem('sidebar-operations-open', value));
                    }
                }"
            >
                <button
                    type="button"
                    class="{{ $sectionButtonClasses }} {{ $sectionButtonStateClasses }}"
                    @click="open = !open"
                    :aria-expanded="open.toString()"
                    aria-controls="sidebar-operations"
                >
                    <span class="flex min-w-0 items-center gap-3">
                        <svg class="h-5 w-5 shrink-0 fill-current" viewBox="0 0 16 16" aria-hidden="true">
                            <path d="M2 3h12v10H2zm2 2v6h8V5zm1 1h2v1H5zm0 2h6v1H5z" />
                        </svg>
                        <span class="truncate leading-5">Operations</span>
                    </span>
                    <span
                        class="inline-flex h-4 w-4 shrink-0 items-center justify-center text-sm leading-none text-gray-400 transition-transform duration-200"
                        :class="open ? 'rotate-90' : ''"
                        aria-hidden="true"
                    >
                        ›
                    </span>
                </button>

                <ul id="sidebar-operations" x-show="open" x-transition.origin.top.duration.200ms x-cloak class="mt-1 space-y-1 pl-3">
                    <li><a href="{{ route('services.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition {{ request()->routeIs('services.*') ? $activeChildClasses : $inactiveChildClasses }}">Services</a></li>
                    <li><a href="{{ route('transactions.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition {{ request()->routeIs('transactions.*') ? $activeChildClasses : $inactiveChildClasses }}">Transactions</a></li>
                </ul>
            </div>

            <div
                x-data="{
                    open: {{ $inventoryOpen ? 'true' : 'false' }},
                    init() {
                        const saved = localStorage.getItem('sidebar-inventory-open');
                        this.open = {{ $inventoryOpen ? 'true' : 'false' }} || saved === 'true';
                        this.$watch('open', value => localStorage.setItem('sidebar-inventory-open', value));
                    }
                }"
            >
                <button
                    type="button"
                    class="{{ $sectionButtonClasses }} {{ $sectionButtonStateClasses }}"
                    @click="open = !open"
                    :aria-expanded="open.toString()"
                    aria-controls="sidebar-inventory"
                >
                    <span class="flex min-w-0 items-center gap-3">
                        <svg class="h-5 w-5 shrink-0 fill-current" viewBox="0 0 16 16" aria-hidden="true">
                            <path d="M2 3h12v10H2zm2 2v2h8V5zm0 4v2h5V9z" />
                        </svg>
                        <span class="truncate leading-5">Inventory Setup</span>
                    </span>
                    <span
                        class="inline-flex h-4 w-4 shrink-0 items-center justify-center text-sm leading-none text-gray-400 transition-transform duration-200"
                        :class="open ? 'rotate-90' : ''"
                        aria-hidden="true"
                    >
                        ›
                    </span>
                </button>

                <ul id="sidebar-inventory" x-show="open" x-transition.origin.top.duration.200ms x-cloak class="mt-1 space-y-1 pl-3">
                    <li><a href="{{ route('products.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition {{ request()->routeIs('products.*') ? $activeChildClasses : $inactiveChildClasses }}">Products</a></li>
                    <li><a href="{{ route('vendors.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition {{ request()->routeIs('vendors.*') ? $activeChildClasses : $inactiveChildClasses }}">Vendors</a></li>
                    <li><a href="{{ route('warehouses.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition {{ request()->routeIs('warehouses.*') ? $activeChildClasses : $inactiveChildClasses }}">Warehouses</a></li>
                    <li><a href="{{ route('purchases.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition {{ request()->routeIs('purchases.*') ? $activeChildClasses : $inactiveChildClasses }}">Purchases</a></li>
                </ul>
            </div>

            <div
                x-data="{
                    open: {{ $routeManagementOpen ? 'true' : 'false' }},
                    init() {
                        const saved = localStorage.getItem('sidebar-route-management-open');
                        this.open = {{ $routeManagementOpen ? 'true' : 'false' }} || saved === 'true';
                        this.$watch('open', value => localStorage.setItem('sidebar-route-management-open', value));
                    }
                }"
            >
                <button
                    type="button"
                    class="{{ $sectionButtonClasses }} {{ $sectionButtonStateClasses }}"
                    @click="open = !open"
                    :aria-expanded="open.toString()"
                    aria-controls="sidebar-route-management"
                >
                    <span class="flex min-w-0 items-center gap-3">
                        <svg class="h-5 w-5 shrink-0 fill-current" viewBox="0 0 16 16" aria-hidden="true">
                            <path d="M2 3h12v2H2zm0 4h9v2H2zm0 4h6v2H2z" />
                        </svg>
                        <span class="truncate leading-5">Route Management</span>
                    </span>
                    <span
                        class="inline-flex h-4 w-4 shrink-0 items-center justify-center text-sm leading-none text-gray-400 transition-transform duration-200"
                        :class="open ? 'rotate-90' : ''"
                        aria-hidden="true"
                    >
                        ›
                    </span>
                </button>

                <ul id="sidebar-route-management" x-show="open" x-transition.origin.top.duration.200ms x-cloak class="mt-1 space-y-1 pl-3">
                    <li><a href="{{ route('routes.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition {{ request()->routeIs('routes.*') ? $activeChildClasses : $inactiveChildClasses }}">Routes</a></li>
                    <li><a href="{{ route('locations.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition {{ request()->routeIs('locations.*') ? $activeChildClasses : $inactiveChildClasses }}">Locations</a></li>
                    <li><a href="{{ route('machines.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition {{ request()->routeIs('machines.*') ? $activeChildClasses : $inactiveChildClasses }}">Machines</a></li>
                    <li><a href="{{ route('bins.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition {{ request()->routeIs('bins.*') ? $activeChildClasses : $inactiveChildClasses }}">Bins</a></li>
                </ul>
            </div>

            <div
                x-data="{
                    open: {{ $accountOpen ? 'true' : 'false' }},
                    init() {
                        const saved = localStorage.getItem('sidebar-account-open');
                        this.open = {{ $accountOpen ? 'true' : 'false' }} || saved === 'true';
                        this.$watch('open', value => localStorage.setItem('sidebar-account-open', value));
                    }
                }"
            >
                <button
                    type="button"
                    class="{{ $sectionButtonClasses }} {{ $sectionButtonStateClasses }}"
                    @click="open = !open"
                    :aria-expanded="open.toString()"
                    aria-controls="sidebar-account"
                >
                    <span class="flex min-w-0 items-center gap-3">
                        <svg class="h-5 w-5 shrink-0 fill-current" viewBox="0 0 16 16" aria-hidden="true">
                            <path d="M8 8a3 3 0 1 0-3-3 3 3 0 0 0 3 3Zm0 1c-2.33 0-7 1.17-7 3.5V14h14v-1.5C15 10.17 10.33 9 8 9Z" />
                        </svg>
                        <span class="truncate leading-5">Account</span>
                    </span>
                    <span
                        class="inline-flex h-4 w-4 shrink-0 items-center justify-center text-sm leading-none text-gray-400 transition-transform duration-200"
                        :class="open ? 'rotate-90' : ''"
                        aria-hidden="true"
                    >
                        ›
                    </span>
                </button>

                <ul id="sidebar-account" x-show="open" x-transition.origin.top.duration.200ms x-cloak class="mt-1 space-y-1 pl-3">
                    <li><a href="{{ route('accounts.select') }}" class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition {{ request()->routeIs('accounts.*') ? $activeChildClasses : $inactiveChildClasses }}">Switch Account</a></li>
                    <li><a href="{{ route('account-users.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition {{ request()->routeIs('account-users.*') ? $activeChildClasses : $inactiveChildClasses }}">Users</a></li>
                    <li><span class="flex cursor-not-allowed items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium text-gray-400 dark:text-gray-500">Settings<span class="ml-auto text-xs">Soon</span></span></li>
                </ul>
            </div>
        </div>
    </aside>
</div>
