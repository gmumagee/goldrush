<x-app-layout title="Add Dictionary Value">
    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-4xl space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 md:text-3xl">Add Dictionary Value</h1>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">New values created from this screen are account-specific, active by default, and automatically assigned the next sort order.</p>
            </div>

            <x-validation-errors />

            <section class="panel">
                <div class="panel-body space-y-5">
                    <form method="POST" action="{{ route('data-dictionary.store') }}" class="space-y-5">
                        @csrf

                        <div>
                            <label for="name" class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">Name</label>
                            <select id="name" name="name" class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100" required>
                                <option value="">Select a name</option>
                                @foreach ($names as $name)
                                    <option value="{{ $name }}" @selected(old('name', $selectedName) === $name)>{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="value" class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">Value</label>
                            <x-input id="value" name="value" type="text" :value="old('value')" maxlength="255" placeholder="Regional Manager" required />
                        </div>

                        <div>
                            <label for="display_name" class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">Display Name</label>
                            <x-input id="display_name" name="display_name" type="text" :value="old('display_name')" maxlength="255" placeholder="Regional Manager" required />
                        </div>

                        <div class="flex flex-wrap gap-3">
                            <x-button>Create Value</x-button>
                            <a href="{{ route('data-dictionary.index', ['name' => old('name', $selectedName)]) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Cancel</a>
                        </div>
                    </form>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
