<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'GoldRush') }}</title>
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
    <div class="min-h-screen px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto flex min-h-[calc(100vh-4rem)] max-w-7xl overflow-hidden rounded-3xl border border-gray-200 bg-white shadow-sm dark:border-gray-700/60 dark:bg-gray-800">
            <div class="flex w-full flex-col justify-between p-8 lg:w-1/2 lg:p-12">
                <div>
                    <div class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-violet-500/15 text-violet-600 dark:text-violet-300">
                        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" class="fill-current">
                            <path d="M27.961 12.95C27.45 6.055 21.945.55 15.05.04v5.001a7.91 7.91 0 0 0 7.91 7.91h5ZM12.95 22.96v5c-6.895-.51-12.4-6.014-12.91-12.91h5a7.91 7.91 0 0 1 7.91 7.91Zm10.01-7.91h5c-.51 6.895-6.015 12.4-12.91 12.91v-5a7.91 7.91 0 0 1 7.91-7.91ZM.04 12.95C.55 6.055 6.055.55 12.95.04v5.001a7.91 7.91 0 0 1-7.91 7.91h-5Z"/>
                        </svg>
                    </div>

                    <h1 class="mt-8 max-w-xl text-4xl font-bold tracking-tight text-gray-900 dark:text-gray-50 sm:text-5xl">
                        Inventory and route operations, now on a proper admin shell.
                    </h1>

                    <p class="mt-6 max-w-2xl text-base leading-7 text-gray-500 dark:text-gray-400">
                        Manage accounts, machines, products, warehouses, services, and transactions from a Tailwind admin interface adapted from the Cruip template.
                    </p>
                </div>

                <div class="mt-10 flex flex-wrap gap-3">
                    <a href="{{ route('login') }}" class="inline-flex items-center rounded-xl bg-gray-900 px-5 py-3 text-sm font-medium text-white transition hover:bg-gray-800 dark:bg-gray-100 dark:text-gray-900 dark:hover:bg-white">Login</a>
                    <a href="{{ route('register') }}" class="inline-flex items-center rounded-xl border border-gray-300 px-5 py-3 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Register</a>
                </div>
            </div>

            <div class="hidden lg:flex lg:w-1/2 lg:flex-col lg:justify-between lg:bg-linear-to-br lg:from-violet-600 lg:to-gray-900 lg:p-12">
                <div class="space-y-6">
                    <div class="rounded-2xl bg-white/10 p-6 backdrop-blur">
                        <p class="text-sm font-medium text-violet-100">Current structure</p>
                        <ul class="mt-4 space-y-3 text-sm text-violet-50/90">
                            <li>Account-based data separation</li>
                            <li>Custom authentication and account selection</li>
                            <li>Laravel 13 with Vite and Tailwind 4</li>
                        </ul>
                    </div>
                    <div class="rounded-2xl bg-white/10 p-6 backdrop-blur">
                        <p class="text-sm font-medium text-violet-100">Integrated from the template</p>
                        <ul class="mt-4 space-y-3 text-sm text-violet-50/90">
                            <li>Dashboard shell and responsive navigation</li>
                            <li>Tailwind admin styling system</li>
                            <li>Authentication page presentation</li>
                        </ul>
                    </div>
                </div>
                <p class="text-sm text-violet-100/80">Use the login or register flow to enter the app.</p>
            </div>
        </div>
    </div>
</body>
</html>
