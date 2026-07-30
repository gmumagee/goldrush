@props(['title' => null, 'description' => null])

@php
    $signupEnabled = config('security.allow_self_registration', false) && \App\Support\Tenancy::isMulti();
    $requestAccessHref = route('marketing.about').'#contact';
    $primaryCtaHref = $signupEnabled ? route('register') : $requestAccessHref;
    $primaryCtaLabel = $signupEnabled ? 'Start your workspace' : 'Request access';
    $currentAccountId = request()->session()->get('current_account_id');
    $appHref = auth()->check()
        ? (
            config('security.require_verified_email', true) && ! auth()->user()?->hasVerifiedEmail()
                ? route('verification.notice')
                : ((\App\Support\Tenancy::isSingle() || $currentAccountId) ? route('dashboard') : route('accounts.select'))
        )
        : route('login');
    $appLabel = auth()->check() ? 'Open app' : 'Login';
    $navItems = [
        ['label' => 'Home', 'route' => 'home'],
        ['label' => 'Features', 'route' => 'marketing.features'],
        ['label' => 'Pricing', 'route' => 'marketing.pricing'],
        ['label' => 'About', 'route' => 'marketing.about'],
    ];
@endphp

<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · '.config('app.name', 'GoldRush') : config('app.name', 'GoldRush') }}</title>
    <meta name="description" content="{{ $description ?? 'GoldRush is a paid vending-management SaaS for routes, machines, inventory, service work, and audit-ready operations.' }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400..700&family=Space+Grotesk:wght@500..700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        if (localStorage.getItem('dark-mode') === 'true') {
            document.documentElement.classList.add('dark');
            document.documentElement.style.colorScheme = 'dark';
        } else {
            document.documentElement.classList.remove('dark');
            document.documentElement.style.colorScheme = 'light';
        }
    </script>
</head>
<body
    x-data="{ navOpen: false }"
    class="bg-[linear-gradient(180deg,#f9f7f1_0%,#f4efe3_18%,#fbfaf8_55%,#ffffff_100%)] font-inter text-gray-700 antialiased dark:bg-[linear-gradient(180deg,#111827_0%,#101826_50%,#0b1220_100%)] dark:text-gray-300"
>
    <div class="fixed inset-x-0 top-0 -z-10 h-[34rem] overflow-hidden">
        <div class="absolute left-1/2 top-[-14rem] h-[34rem] w-[34rem] -translate-x-1/2 rounded-full bg-[radial-gradient(circle,rgba(200,164,88,0.34),rgba(200,164,88,0.08)_45%,transparent_72%)] blur-3xl"></div>
        <div class="absolute right-[-8rem] top-28 h-72 w-72 rounded-full bg-[radial-gradient(circle,rgba(31,41,55,0.18),transparent_70%)] blur-3xl dark:bg-[radial-gradient(circle,rgba(217,185,93,0.12),transparent_70%)]"></div>
    </div>

    <header class="sticky top-0 z-40 border-b border-white/40 bg-white/70 backdrop-blur-xl dark:border-gray-800/80 dark:bg-gray-950/70">
        <div class="marketing-section">
            <div class="marketing-shell flex h-20 items-center justify-between gap-4">
                <a href="{{ route('home') }}" class="flex items-center gap-3 text-gray-900 dark:text-gray-100">
                    <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[linear-gradient(135deg,#d6b15a_0%,#a87918_100%)] text-white shadow-[0_18px_40px_-18px_rgba(168,121,24,0.85)]">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" class="h-7 w-7 fill-current">
                            <path d="M16 3 4 10v12l12 7 12-7V10L16 3Zm0 3.2 8.8 5.13L16 16.45 7.2 11.33 16 6.2Zm-9 7.73 7.6 4.41v7.84L7 21.74v-7.81Zm11.6 12.25v-7.84l7.4-4.29v7.69l-7.4 4.44Z"/>
                        </svg>
                    </span>
                    <span>
                        <span class="block font-['Space_Grotesk'] text-xl font-semibold tracking-tight">GoldRush</span>
                        <span class="block text-xs uppercase tracking-[0.28em] text-gray-500 dark:text-gray-400">Vending Operations Management</span>
                    </span>
                </a>

                <nav class="hidden items-center gap-8 lg:flex">
                    @foreach ($navItems as $item)
                        <a
                            href="{{ route($item['route']) }}"
                            class="text-sm font-medium transition {{ request()->routeIs($item['route']) ? 'text-gray-950 dark:text-white' : 'text-gray-600 hover:text-gray-950 dark:text-gray-300 dark:hover:text-white' }}"
                        >
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </nav>

                <div class="hidden items-center gap-3 lg:flex">
                    <a href="{{ $appHref }}" class="inline-flex items-center rounded-full border border-gray-300/80 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:border-gray-400 hover:bg-white dark:border-gray-600 dark:text-gray-100 dark:hover:bg-gray-800">
                        {{ $appLabel }}
                    </a>
                    <a href="{{ $primaryCtaHref }}" class="inline-flex items-center rounded-full bg-gray-950 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-gray-800 dark:bg-[linear-gradient(135deg,#d6b15a_0%,#a87918_100%)] dark:text-gray-950 dark:hover:brightness-105">
                        {{ $primaryCtaLabel }}
                    </a>
                </div>

                <button
                    type="button"
                    class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-gray-300/80 text-gray-700 dark:border-gray-600 dark:text-gray-100 lg:hidden"
                    @click="navOpen = !navOpen"
                    aria-controls="marketing-mobile-nav"
                    :aria-expanded="navOpen"
                >
                    <span class="sr-only">Toggle navigation</span>
                    <svg class="h-5 w-5 fill-current" viewBox="0 0 24 24">
                        <rect x="4" y="5" width="16" height="2" />
                        <rect x="4" y="11" width="16" height="2" />
                        <rect x="4" y="17" width="16" height="2" />
                    </svg>
                </button>
            </div>
        </div>

        <div
            id="marketing-mobile-nav"
            x-cloak
            x-show="navOpen"
            x-transition.opacity
            class="border-t border-white/50 bg-white/95 dark:border-gray-800/80 dark:bg-gray-950/95 lg:hidden"
        >
            <div class="marketing-section py-5">
                <div class="marketing-shell space-y-5">
                    <div class="grid gap-3">
                        @foreach ($navItems as $item)
                            <a
                                href="{{ route($item['route']) }}"
                                class="rounded-2xl px-4 py-3 text-sm font-medium transition {{ request()->routeIs($item['route']) ? 'bg-gray-950 text-white dark:bg-gray-100 dark:text-gray-950' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200' }}"
                            >
                                {{ $item['label'] }}
                            </a>
                        @endforeach
                    </div>
                    <div class="flex flex-col gap-3">
                        <a href="{{ $appHref }}" class="inline-flex items-center justify-center rounded-full border border-gray-300/80 px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-600 dark:text-gray-100">
                            {{ $appLabel }}
                        </a>
                        <a href="{{ $primaryCtaHref }}" class="inline-flex items-center justify-center rounded-full bg-gray-950 px-4 py-3 text-sm font-semibold text-white dark:bg-[linear-gradient(135deg,#d6b15a_0%,#a87918_100%)] dark:text-gray-950">
                            {{ $primaryCtaLabel }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main>
        @if (session('status'))
            <div class="marketing-section pt-6">
                <div class="marketing-shell">
                    <div class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-900/60 dark:bg-green-500/10 dark:text-green-300">
                        {{ session('status') }}
                    </div>
                </div>
            </div>
        @endif

        {{ $slot }}
    </main>

    <footer class="border-t border-gray-200/80 bg-white/70 py-16 dark:border-gray-800/80 dark:bg-gray-950/65">
        <div class="marketing-section">
            <div class="marketing-shell grid gap-10 lg:grid-cols-[1.4fr_1fr_1fr_1fr]">
                <div class="max-w-md">
                    <p class="font-['Space_Grotesk'] text-2xl font-semibold text-gray-950 dark:text-white">GoldRush</p>
                    <p class="mt-4 text-sm leading-7 text-gray-600 dark:text-gray-300">
                        Affordable Vending Management
                    </p>
                    <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                        Replace placeholder contact details before launch. Current placeholder: <a href="mailto:hello@goldrush.example" class="font-medium text-gray-900 underline decoration-gray-300 underline-offset-4 dark:text-white">hello@goldrush.example</a>
                    </p>
                </div>

                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.2em] text-gray-500 dark:text-gray-400">Product</p>
                    <ul class="mt-4 space-y-3 text-sm">
                        <li><a href="{{ route('home') }}" class="transition hover:text-gray-950 dark:hover:text-white">Home</a></li>
                        <li><a href="{{ route('marketing.features') }}" class="transition hover:text-gray-950 dark:hover:text-white">Features</a></li>
                        <li><a href="{{ route('marketing.pricing') }}" class="transition hover:text-gray-950 dark:hover:text-white">Pricing</a></li>
                    </ul>
                </div>

                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.2em] text-gray-500 dark:text-gray-400">Company</p>
                    <ul class="mt-4 space-y-3 text-sm">
                        <li><a href="{{ route('marketing.about') }}" class="transition hover:text-gray-950 dark:hover:text-white">About & Contact</a></li>
                        <li><a href="{{ $appHref }}" class="transition hover:text-gray-950 dark:hover:text-white">{{ $appLabel }}</a></li>
                        <li><a href="{{ $primaryCtaHref }}" class="transition hover:text-gray-950 dark:hover:text-white">{{ $primaryCtaLabel }}</a></li>
                    </ul>
                </div>

                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.2em] text-gray-500 dark:text-gray-400">Focus areas</p>
                    <ul class="mt-4 space-y-3 text-sm text-gray-600 dark:text-gray-300">
                        <li>Route planning and weekly service scheduling</li>
                        <li>Machine, bin, warehouse, and product visibility</li>
                        <li>Audit-ready operations for multi-user teams</li>
                    </ul>
                </div>
            </div>

            <div class="marketing-shell mt-12 flex flex-col gap-3 border-t border-gray-200/80 pt-6 text-sm text-gray-500 dark:border-gray-800/80 dark:text-gray-400 sm:flex-row sm:items-center sm:justify-between">
                <p>&copy; {{ now()->year }} GoldRush. All rights reserved.</p>
                <p>Pricing and company copy on these pages are placeholders you can edit before launch.</p>
            </div>
        </div>
    </footer>
</body>
</html>
