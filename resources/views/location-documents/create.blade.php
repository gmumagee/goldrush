<x-app-layout title="Upload Location Document">
    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-3xl space-y-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 md:text-3xl">Upload Location Document</h1>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Location: {{ $location->location_name }}</p>
                </div>
                <a href="{{ route('locations.show', $location) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Back to Location</a>
            </div>

            <x-validation-errors />

            <section class="panel">
                <div class="panel-body">
                    <form method="POST" action="{{ route('locations.documents.store', $location) }}" enctype="multipart/form-data" class="space-y-5">
                        @csrf

                        <div>
                            <x-label for="document_type" value="Document Type" />
                            <select id="document_type" name="document_type" class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100">
                                <option value="">Select a document type</option>
                                @foreach ($documentTypeOptions as $documentType)
                                    <option value="{{ $documentType->value }}" @selected(old('document_type') === $documentType->value)>{{ $documentType->displayLabel() }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <x-label for="title" value="Title" />
                            <x-input id="title" name="title" type="text" :value="old('title')" />
                        </div>

                        <div>
                            <x-label for="description" value="Description" />
                            <textarea id="description" name="description" rows="4" class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100">{{ old('description') }}</textarea>
                        </div>

                        <div>
                            <x-label for="file" value="File" />
                            <x-input id="file" name="file" type="file" required />
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Allowed types: PDF, JPG, JPEG, PNG, DOC, DOCX, XLS, XLSX, TXT. Maximum size: 10 MB.</p>
                        </div>

                        <div class="flex items-center justify-end gap-3">
                            <a href="{{ route('locations.show', $location) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Cancel</a>
                            <x-button>Upload Document</x-button>
                        </div>
                    </form>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
