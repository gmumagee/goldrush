@php
    $currentAccount = session('current_account_id')
        ? \App\Models\Account::query()->select(['id', 'account_name', 'slug'])->find(session('current_account_id'))
        : null;
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

        <div class="space-y-8">
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
                            class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition {{ request()->routeIs('dashboard') ? 'bg-violet-500/10 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300' : 'text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700/60' }}"
                        >
                            <svg class="h-5 w-5 shrink-0 fill-current" viewBox="0 0 16 16" aria-hidden="true">
                                <path d="M5.936.278A7.983 7.983 0 0 1 8 0a8 8 0 1 1-8 8c0-.722.104-1.413.278-2.064a1 1 0 1 1 1.932.516A5.99 5.99 0 0 0 2 8a6 6 0 1 0 6-6c-.53 0-1.045.076-1.548.21A1 1 0 1 1 5.936.278Z" />
                                <path d="M6.068 7.482A2.003 2.003 0 0 0 8 10a2 2 0 1 0-.518-3.932L3.707 2.293a1 1 0 0 0-1.414 1.414l3.775 3.775Z" />
                            </svg>
                            <span class="opacity-100">Dashboard</span>
                        </a>
                    </li>

                    <li>
                        <a
                            href="{{ route('accounts.select') }}"
                            class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700/60"
                        >
                            <svg class="h-5 w-5 shrink-0 fill-current" viewBox="0 0 16 16" aria-hidden="true">
                                <path d="M2 2h12v3H2zm0 5h12v7H2zm2 2v3h3V9zm5 0v1h3V9z" />
                            </svg>
                            <span class="opacity-100">Switch account</span>
                        </a>
                    </li>
                </ul>
            </div>

            <div>
                <h3 class="pl-3 text-xs font-semibold uppercase text-gray-400 dark:text-gray-500">
                    <span class="hidden w-6 text-center lg:hidden" aria-hidden="true">•••</span>
                    <span class="block">Machines</span>
                </h3>

                <ul class="mt-3 space-y-1">
                    <li>
                        <a
                            href="{{ route('machines.index') }}"
                            class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition {{ request()->routeIs('machines.index') ? 'bg-violet-500/10 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300' : 'text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700/60' }}"
                        >
                            <svg class="h-5 w-5 shrink-0 fill-current" viewBox="0 0 16 16" aria-hidden="true">
                                <path d="M2 3h12v2H2zm1 4h10v6H3zm2 2v2h2V9zm4 0v2h2V9z" />
                            </svg>
                            <span class="opacity-100">Machine Inventory</span>
                        </a>
                    </li>
                    <li>
                        <a
                            href="{{ route('machines.create') }}"
                            class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition {{ request()->routeIs('machines.create') ? 'bg-violet-500/10 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300' : 'text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700/60' }}"
                        >
                            <svg class="h-5 w-5 shrink-0 fill-current" viewBox="0 0 16 16" aria-hidden="true">
                                <path d="M7 1h2v6h6v2H9v6H7V9H1V7h6z" />
                            </svg>
                            <span class="opacity-100">Add Machine</span>
                        </a>
                    </li>
                </ul>
            </div>

            <div>
                <h3 class="pl-3 text-xs font-semibold uppercase text-gray-400 dark:text-gray-500">
                    <span class="hidden w-6 text-center lg:hidden" aria-hidden="true">•••</span>
                    <span class="block">Products</span>
                </h3>

                <ul class="mt-3 space-y-1">
                    <li>
                        <a href="{{ route('products.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition {{ request()->routeIs('products.index') ? 'bg-violet-500/10 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300' : 'text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700/60' }}">
                            <svg class="h-5 w-5 shrink-0 fill-current" viewBox="0 0 16 16"><path d="M2 3h12v10H2zm2 2v2h8V5zm0 4v2h5V9z" /></svg>
                            <span class="opacity-100">Product Inventory</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('products.create') }}" class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition {{ request()->routeIs('products.create') ? 'bg-violet-500/10 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300' : 'text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700/60' }}">
                            <svg class="h-5 w-5 shrink-0 fill-current" viewBox="0 0 16 16"><path d="M7 1h2v6h6v2H9v6H7V9H1V7h6z" /></svg>
                            <span class="opacity-100">Add Product</span>
                        </a>
                    </li>
                </ul>
            </div>

            <div>
                <h3 class="pl-3 text-xs font-semibold uppercase text-gray-400 dark:text-gray-500">
                    <span class="hidden w-6 text-center lg:hidden" aria-hidden="true">•••</span>
                    <span class="block">Locations</span>
                </h3>

                <ul class="mt-3 space-y-1">
                    <li>
                        <a href="{{ route('locations.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition {{ request()->routeIs('locations.index') ? 'bg-violet-500/10 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300' : 'text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700/60' }}">
                            <svg class="h-5 w-5 shrink-0 fill-current" viewBox="0 0 16 16"><path d="M8 1l6 4v9H2V5zm0 2.2L4 5.7V12h8V5.7z" /></svg>
                            <span class="opacity-100">Location Inventory</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('locations.create') }}" class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition {{ request()->routeIs('locations.create') ? 'bg-violet-500/10 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300' : 'text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700/60' }}">
                            <svg class="h-5 w-5 shrink-0 fill-current" viewBox="0 0 16 16"><path d="M7 1h2v6h6v2H9v6H7V9H1V7h6z" /></svg>
                            <span class="opacity-100">Add Location</span>
                        </a>
                    </li>
                </ul>
            </div>

            <div>
                <h3 class="pl-3 text-xs font-semibold uppercase text-gray-400 dark:text-gray-500">
                    <span class="hidden w-6 text-center lg:hidden" aria-hidden="true">•••</span>
                    <span class="block">Warehouses</span>
                </h3>

                <ul class="mt-3 space-y-1">
                    <li>
                        <a href="{{ route('warehouses.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition {{ request()->routeIs('warehouses.index') ? 'bg-violet-500/10 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300' : 'text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700/60' }}">
                            <svg class="h-5 w-5 shrink-0 fill-current" viewBox="0 0 16 16"><path d="M2 5l6-3 6 3v7H2zm2 1.2V10h8V6.2L8 4.2z" /></svg>
                            <span class="opacity-100">Warehouse Inventory</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('warehouses.create') }}" class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition {{ request()->routeIs('warehouses.create') ? 'bg-violet-500/10 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300' : 'text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700/60' }}">
                            <svg class="h-5 w-5 shrink-0 fill-current" viewBox="0 0 16 16"><path d="M7 1h2v6h6v2H9v6H7V9H1V7h6z" /></svg>
                            <span class="opacity-100">Add Warehouse</span>
                        </a>
                    </li>
                </ul>
            </div>

            <div>
                <h3 class="pl-3 text-xs font-semibold uppercase text-gray-400 dark:text-gray-500">
                    <span class="hidden w-6 text-center lg:hidden" aria-hidden="true">•••</span>
                    <span class="block">Vendors</span>
                </h3>

                <ul class="mt-3 space-y-1">
                    <li>
                        <a href="{{ route('vendors.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition {{ request()->routeIs('vendors.index') ? 'bg-violet-500/10 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300' : 'text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700/60' }}">
                            <svg class="h-5 w-5 shrink-0 fill-current" viewBox="0 0 16 16"><path d="M3 3h10v10H3zm2 2v6h6V5z" /></svg>
                            <span class="opacity-100">Vendor Inventory</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('vendors.create') }}" class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition {{ request()->routeIs('vendors.create') ? 'bg-violet-500/10 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300' : 'text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700/60' }}">
                            <svg class="h-5 w-5 shrink-0 fill-current" viewBox="0 0 16 16"><path d="M7 1h2v6h6v2H9v6H7V9H1V7h6z" /></svg>
                            <span class="opacity-100">Add Vendor</span>
                        </a>
                    </li>
                </ul>
            </div>

            <div>
                <h3 class="pl-3 text-xs font-semibold uppercase text-gray-400 dark:text-gray-500">
                    <span class="hidden w-6 text-center lg:hidden" aria-hidden="true">•••</span>
                    <span class="block">Routes</span>
                </h3>

                <ul class="mt-3 space-y-1">
                    <li>
                        <a href="{{ route('routes.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition {{ request()->routeIs('routes.index') ? 'bg-violet-500/10 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300' : 'text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700/60' }}">
                            <svg class="h-5 w-5 shrink-0 fill-current" viewBox="0 0 16 16"><path d="M2 4h12v2H2zm0 6h12v2H2z" /></svg>
                            <span class="opacity-100">Route Inventory</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('routes.create') }}" class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition {{ request()->routeIs('routes.create') ? 'bg-violet-500/10 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300' : 'text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700/60' }}">
                            <svg class="h-5 w-5 shrink-0 fill-current" viewBox="0 0 16 16"><path d="M7 1h2v6h6v2H9v6H7V9H1V7h6z" /></svg>
                            <span class="opacity-100">Add Route</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </aside>
</div>
