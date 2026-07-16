<x-app-layout title="Location Details">
    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-7xl space-y-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 md:text-3xl">{{ $location->location_name }}</h1>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ $location->routes->pluck('route_name')->join(' · ') ?: ($location->route?->route_name ?? 'No route') }}</p>
                </div>
                <div class="flex gap-3">
                    <a href="{{ route('locations.index') }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Back to Locations</a>
                    <a href="{{ route('calendar-events.create', ['source_type' => 'location', 'source_id' => $location->id]) }}" class="inline-flex items-center rounded-xl border border-violet-300 px-4 py-2 text-sm font-medium text-violet-700 transition hover:bg-violet-50 dark:border-violet-500/40 dark:text-violet-300 dark:hover:bg-violet-500/10">Schedule Event</a>
                    <a href="{{ route('locations.edit', $location) }}" class="inline-flex items-center rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-violet-500">Edit Location</a>
                </div>
            </div>

            @if (session('status'))
                <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-900/60 dark:bg-green-500/10 dark:text-green-300">{{ session('status') }}</div>
            @endif

            <x-validation-errors />

            <section class="panel">
                <div class="panel-body">
                    <dl class="grid gap-4 text-sm md:grid-cols-2 xl:grid-cols-3">
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Primary Route</dt>
                            <dd class="mt-1 text-gray-800 dark:text-gray-100">{{ $location->route?->route_name ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Assigned Routes</dt>
                            <dd class="mt-1 text-gray-800 dark:text-gray-100">{{ $location->routes->pluck('route_name')->join(', ') ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Address</dt>
                            <dd class="mt-1 text-gray-800 dark:text-gray-100">{{ $location->address ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">City</dt>
                            <dd class="mt-1 text-gray-800 dark:text-gray-100">{{ $location->city ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">State</dt>
                            <dd class="mt-1 text-gray-800 dark:text-gray-100">{{ $location->state ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Zip Code</dt>
                            <dd class="mt-1 text-gray-800 dark:text-gray-100">{{ $location->zip_code ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Legacy Contact Name</dt>
                            <dd class="mt-1 text-gray-800 dark:text-gray-100">{{ $location->contact_name ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Legacy Contact Phone</dt>
                            <dd class="mt-1 text-gray-800 dark:text-gray-100">{{ $location->contact_phone ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Legacy Contact Email</dt>
                            <dd class="mt-1 text-gray-800 dark:text-gray-100">{{ $location->contact_email ?: '—' }}</dd>
                        </div>
                    </dl>
                </div>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Contacts</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Attach reusable contacts to this location and manage location-specific roles.</p>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <a href="{{ route('locations.contacts.create', $location) }}#attach-existing" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Attach Existing Contact</a>
                        <a href="{{ route('locations.contacts.create', $location) }}#create-new" class="inline-flex items-center rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-violet-500">Add Contact</a>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700/60">
                        <thead class="bg-gray-50 dark:bg-gray-800/80">
                            <tr>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Name</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Role</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Organization</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Title</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Email</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Phone</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Mobile Phone</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Primary</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Notes</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700/60">
                            @forelse ($location->locationContacts as $locationContact)
                                @php
                                    $contact = $locationContact->contact;
                                    $roleKey = strtolower(trim((string) $locationContact->contact_role));
                                @endphp
                                <tr class="bg-white dark:bg-gray-800">
                                    <td class="px-5 py-4 font-medium text-gray-800 dark:text-gray-100">
                                        @if ($contact)
                                            <a href="{{ route('contacts.show', $contact) }}" class="hover:text-violet-600 dark:hover:text-violet-300">{{ $contact->display_name }}</a>
                                        @else
                                            Unknown Contact
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $locationContactRoleLabels[$roleKey] ?? ($locationContact->contact_role ?: '—') }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $contact?->organization ?: '—' }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $contact?->title ?: '—' }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $contact?->email ?: '—' }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $contact?->phone ?: '—' }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $contact?->mobile_phone ?: '—' }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">
                                        @if ($locationContact->is_primary)
                                            <span class="inline-flex rounded-full bg-green-100 px-2.5 py-1 text-xs font-medium text-green-800 dark:bg-green-500/15 dark:text-green-300">Primary</span>
                                        @else
                                            No
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $locationContact->notes ?: '—' }}</td>
                                    <td class="px-5 py-4">
                                        <div class="flex flex-wrap gap-2">
                                            <a href="{{ route('locations.contacts.edit', [$location, $locationContact]) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Edit Relationship</a>
                                            <form method="POST" action="{{ route('locations.contacts.destroy', [$location, $locationContact]) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="inline-flex items-center rounded-xl border border-red-300 px-3 py-1.5 text-xs font-medium text-red-700 transition hover:bg-red-50 dark:border-red-500/40 dark:text-red-300 dark:hover:bg-red-500/10">Remove From Location</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr class="bg-white dark:bg-gray-800">
                                    <td colspan="10" class="px-5 py-8 text-center text-gray-500 dark:text-gray-400">No contacts are attached to this location yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Documents</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Store private location contracts, insurance records, photos, and other supporting files.</p>
                    </div>
                    @if ($canManageDocuments)
                        <a href="{{ route('locations.documents.create', $location) }}" class="inline-flex items-center rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-violet-500">Upload Document</a>
                    @endif
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700/60">
                        <thead class="bg-gray-50 dark:bg-gray-800/80">
                            <tr>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Title</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Document Type</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Original Filename</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">File Size</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Uploaded By</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Uploaded At</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700/60">
                            @forelse ($location->documents as $document)
                                @php
                                    $documentTypeKey = strtolower(trim((string) $document->document_type));
                                @endphp
                                <tr class="bg-white dark:bg-gray-800">
                                    <td class="px-5 py-4 font-medium text-gray-800 dark:text-gray-100">
                                        <a href="{{ route('locations.documents.show', [$location, $document]) }}" class="hover:text-violet-600 dark:hover:text-violet-300">{{ $document->title ?: $document->original_filename }}</a>
                                    </td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $locationDocumentTypeLabels[$documentTypeKey] ?? ($document->document_type ?: '—') }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $document->original_filename }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $document->file_size ? \Illuminate\Support\Number::fileSize((int) $document->file_size) : '—' }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $document->uploadedBy?->name ?: '—' }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $document->created_at?->format('d-m-Y H:i') ?: '—' }}</td>
                                    <td class="px-5 py-4">
                                        <div class="flex flex-wrap gap-2">
                                            <a href="{{ route('locations.documents.download', [$location, $document]) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Download</a>
                                            @if ($canManageDocuments)
                                                <form method="POST" action="{{ route('locations.documents.destroy', [$location, $document]) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="inline-flex items-center rounded-xl border border-red-300 px-3 py-1.5 text-xs font-medium text-red-700 transition hover:bg-red-50 dark:border-red-500/40 dark:text-red-300 dark:hover:bg-red-500/10">Delete</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr class="bg-white dark:bg-gray-800">
                                    <td colspan="7" class="px-5 py-8 text-center text-gray-500 dark:text-gray-400">No documents have been uploaded for this location yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Machines</h2>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700/60">
                        <thead class="bg-gray-50 dark:bg-gray-800/80">
                            <tr>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Type</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Serial</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Status</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Bins</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700/60">
                            @forelse ($location->machines as $machine)
                                <tr class="bg-white dark:bg-gray-800">
                                    <td class="px-5 py-4 font-medium text-gray-800 dark:text-gray-100">{{ $machine->type }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $machine->serial_number ?: '—' }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $machine->status }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $machine->bins->count() }}</td>
                                </tr>
                            @empty
                                <tr class="bg-white dark:bg-gray-800">
                                    <td colspan="4" class="px-5 py-8 text-center text-gray-500 dark:text-gray-400">No machines are assigned to this location.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
