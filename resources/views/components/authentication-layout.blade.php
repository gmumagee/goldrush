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
<body class="font-inter bg-gray-100 text-gray-600 antialiased dark:bg-gray-900 dark:text-gray-400">
    <main class="min-h-screen bg-white dark:bg-gray-900">
        <div class="relative flex min-h-screen">
            <div class="flex w-full flex-col md:w-1/2">
                <div class="flex h-16 items-center justify-between px-4 sm:px-6 lg:px-8">
                    <a class="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-violet-500/15 text-violet-600 dark:text-violet-300" href="{{ auth()->check() ? route('accounts.select') : url('/') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" class="fill-current">
                            <path d="M27.961 12.95C27.45 6.055 21.945.55 15.05.04v5.001a7.91 7.91 0 0 0 7.91 7.91h5ZM12.95 22.96v5c-6.895-.51-12.4-6.014-12.91-12.91h5a7.91 7.91 0 0 1 7.91 7.91Zm10.01-7.91h5c-.51 6.895-6.015 12.4-12.91 12.91v-5a7.91 7.91 0 0 1 7.91-7.91ZM.04 12.95C.55 6.055 6.055.55 12.95.04v5.001a7.91 7.91 0 0 1-7.91 7.91h-5Z"/>
                        </svg>
                    </a>
                    <x-theme-toggle />
                </div>

                <div class="flex flex-1 items-center px-4 py-10 sm:px-6 lg:px-8">
                    <div class="mx-auto w-full max-w-md">
                        {{ $slot }}
                    </div>
                </div>
            </div>

            <div class="hidden md:flex md:w-1/2 md:flex-col md:justify-between md:bg-linear-to-br md:from-violet-600 md:to-gray-900 md:p-12">
                <div class="space-y-6">
                    <div class="rounded-2xl bg-white/10 p-6 text-violet-50 backdrop-blur">
                        <p class="text-sm font-semibold text-violet-100">GoldRush Operations</p>
                        <h2 class="mt-4 text-3xl font-bold">A cleaner admin shell for account-based vending data.</h2>
                    </div>

                    <div class="rounded-2xl bg-white/10 p-6 backdrop-blur">
                        <ul class="space-y-3 text-sm text-violet-50/90">
                            <li>Track machines, products, routes, and services</li>
                            <li>Switch safely between tenant accounts</li>
                            <li>Use a responsive dashboard adapted from Cruip Mosaic</li>
                        </ul>
                    </div>
                </div>

                <p class="text-sm text-violet-100/80">Laravel 13 · Tailwind 4 · Account-scoped inventory operations</p>
            </div>
        </div>
    </main>
</body>
</html>
