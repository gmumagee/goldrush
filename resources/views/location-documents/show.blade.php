<x-app-layout title="Location Document">
    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-4xl space-y-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 md:text-3xl">{{ $document->title ?: $document->original_filename }}</h1>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Location: {{ $location->location_name }}</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <a href="{{ route('locations.show', $location) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Back to Location</a>
                    <a href="{{ route('locations.documents.download', [$location, $document]) }}" class="inline-flex items-center rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-violet-500">Download</a>
                </div>
            </div>

            @if (session('status'))
                <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-900/60 dark:bg-green-500/10 dark:text-green-300">{{ session('status') }}</div>
            @endif

            <x-validation-errors />

            <section class="panel">
                <div class="panel-body">
                    @php
                        $documentTypeKey = strtolower(trim((string) $document->document_type));
                    @endphp
                    <dl class="grid gap-4 text-sm md:grid-cols-2">
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Document Type</dt>
                            <dd class="mt-1 text-gray-800 dark:text-gray-100">{{ $documentTypeLabels[$documentTypeKey] ?? ($document->document_type ?: '—') }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Original Filename</dt>
                            <dd class="mt-1 text-gray-800 dark:text-gray-100">{{ $document->original_filename }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">File Size</dt>
                            <dd class="mt-1 text-gray-800 dark:text-gray-100">{{ $document->file_size ? \Illuminate\Support\Number::fileSize((int) $document->file_size) : '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Mime Type</dt>
                            <dd class="mt-1 text-gray-800 dark:text-gray-100">{{ $document->mime_type ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Uploaded By</dt>
                            <dd class="mt-1 text-gray-800 dark:text-gray-100">{{ $document->uploadedBy?->name ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Uploaded At</dt>
                            <dd class="mt-1 text-gray-800 dark:text-gray-100">{{ $document->created_at?->format('d-m-Y H:i') ?: '—' }}</dd>
                        </div>
                        <div class="md:col-span-2">
                            <dt class="text-gray-500 dark:text-gray-400">Description</dt>
                            <dd class="mt-1 whitespace-pre-line text-gray-800 dark:text-gray-100">{{ $document->description ?: '—' }}</dd>
                        </div>
                    </dl>
                </div>
            </section>

            @if ($canManageDocuments)
                <section class="panel">
                    <div class="panel-body">
                        <div class="flex justify-end">
                            <form method="POST" action="{{ route('locations.documents.destroy', [$location, $document]) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="inline-flex items-center rounded-xl border border-red-300 px-4 py-2 text-sm font-medium text-red-700 transition hover:bg-red-50 dark:border-red-500/40 dark:text-red-300 dark:hover:bg-red-500/10">Delete Document</button>
                            </form>
                        </div>
                    </div>
                </section>
            @endif
        </div>
    </div>
</x-app-layout>
