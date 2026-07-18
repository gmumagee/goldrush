<x-app-layout title="Edit Dictionary Value">
    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-4xl space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 md:text-3xl">Edit Dictionary Value</h1>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Name stays fixed to avoid breaking existing references.</p>
            </div>

            <x-validation-errors />

            <section class="panel">
                <div class="panel-body space-y-5">
                    <form method="POST" action="{{ route('data-dictionary.update', $entry) }}" class="space-y-5">
                        @csrf
                        @method('PUT')

                        <div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">Name</label>
                                <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800/60 dark:text-gray-200">{{ $entry->name }}</div>
                            </div>
                        </div>

                        <div class="grid gap-5 md:grid-cols-2">
                            <div>
                                <label for="value" class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">Value</label>
                                <x-input id="value" name="value" type="text" :value="old('value', $entry->value)" maxlength="255" required />
                            </div>

                            <div>
                                <label for="display_name" class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">Display Name</label>
                                <x-input id="display_name" name="display_name" type="text" :value="old('display_name', $entry->displayLabel())" maxlength="255" required />
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-3">
                            <x-button>Save Changes</x-button>
                            <a href="{{ route('data-dictionary.index', ['name' => $entry->name]) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Back</a>
                        </div>
                    </form>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
