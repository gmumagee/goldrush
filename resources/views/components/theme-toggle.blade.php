<button
    type="button"
    x-data="{ dark: document.documentElement.classList.contains('dark') }"
    @click="
        dark = !dark;
        document.documentElement.classList.toggle('dark', dark);
        document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
        localStorage.setItem('dark-mode', dark ? 'true' : 'false');
    "
    class="inline-flex items-center rounded-xl border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
>
    <span x-show="!dark">Dark mode</span>
    <span x-show="dark" x-cloak>Light mode</span>
</button>
