@props(['title' => null])

<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · '.config('app.name', 'GoldRush') : config('app.name', 'GoldRush') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400..700&display=swap" rel="stylesheet" />
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
    x-data="{ sidebarOpen: false, sidebarExpanded: localStorage.getItem('sidebar-expanded') === 'true' }"
    x-init="$watch('sidebarExpanded', value => localStorage.setItem('sidebar-expanded', value))"
    class="font-inter bg-gray-100 text-gray-600 antialiased dark:bg-gray-900 dark:text-gray-400"
    :class="{ 'sidebar-expanded': sidebarExpanded }"
>
    <div class="flex h-[100dvh] overflow-hidden">
        <x-app.sidebar />

        <div class="relative flex flex-1 flex-col overflow-y-auto overflow-x-hidden">
            <x-app.header />

            @if (\App\Support\Demo::isEnabled())
                <div class="border-b border-amber-200 bg-amber-50/95 px-4 py-3 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100 sm:px-6 lg:px-8">
                    <div class="mx-auto flex w-full max-w-7xl flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="font-medium">
                            DEMO ENVIRONMENT — data resets nightly.
                        </div>
                        <a
                            href="{{ \App\Support\Demo::bannerCtaUrl() }}"
                            class="inline-flex items-center justify-center rounded-full border border-amber-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-amber-900 transition hover:bg-amber-100 dark:border-amber-400/40 dark:bg-transparent dark:text-amber-100 dark:hover:bg-amber-500/10"
                        >
                            {{ \App\Support\Demo::bannerCtaLabel() }}
                        </a>
                    </div>
                </div>
            @endif

            <main class="grow">
                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>
