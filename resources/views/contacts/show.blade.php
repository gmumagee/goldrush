<x-app-layout title="Contact Details">
    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-7xl space-y-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 md:text-3xl">{{ $contact->display_name }}</h1>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Reusable contact details and the locations that use this contact.</p>
                </div>
                <div class="flex gap-3">
                    <a href="{{ route('contacts.index') }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Back to Contacts</a>
                    <a href="{{ route('contacts.edit', $contact) }}" class="inline-flex items-center rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-violet-500">Edit Contact</a>
                </div>
            </div>

            @if (session('status'))
                <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-900/60 dark:bg-green-500/10 dark:text-green-300">{{ session('status') }}</div>
            @endif

            <x-validation-errors />

            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Contact Details</h2>
                    </div>
                </div>
                <div class="panel-body">
                    <dl class="grid gap-4 text-sm md:grid-cols-2 xl:grid-cols-4">
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">First Name</dt>
                            <dd class="mt-1 text-gray-800 dark:text-gray-100">{{ $contact->first_name ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Last Name</dt>
                            <dd class="mt-1 text-gray-800 dark:text-gray-100">{{ $contact->last_name ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Organization</dt>
                            <dd class="mt-1 text-gray-800 dark:text-gray-100">{{ $contact->organization ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Title</dt>
                            <dd class="mt-1 text-gray-800 dark:text-gray-100">{{ $contact->title ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Email</dt>
                            <dd class="mt-1 text-gray-800 dark:text-gray-100">{{ $contact->email ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Phone</dt>
                            <dd class="mt-1 text-gray-800 dark:text-gray-100">{{ $contact->phone ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Mobile Phone</dt>
                            <dd class="mt-1 text-gray-800 dark:text-gray-100">{{ $contact->mobile_phone ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Notes</dt>
                            <dd class="mt-1 text-gray-800 dark:text-gray-100">{{ $contact->notes ?: '—' }}</dd>
                        </div>
                    </dl>
                </div>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Locations Using This Contact</h2>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700/60">
                        <thead class="bg-gray-50 dark:bg-gray-800/80">
                            <tr>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Location</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Role</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Primary</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Notes</th>
                                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700/60">
                            @forelse ($contact->locationContacts as $locationContact)
                                <tr class="bg-white dark:bg-gray-800">
                                    <td class="px-5 py-4 font-medium text-gray-800 dark:text-gray-100">
                                        <a href="{{ route('locations.show', $locationContact->location) }}" class="hover:text-violet-600 dark:hover:text-violet-300">
                                            {{ $locationContact->location?->location_name ?? 'Unknown Location' }}
                                        </a>
                                    </td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $locationContact->contact_role ?: '—' }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $locationContact->is_primary ? 'Yes' : 'No' }}</td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $locationContact->notes ?: '—' }}</td>
                                    <td class="px-5 py-4">
                                        @if ($locationContact->location)
                                            <div class="flex flex-wrap gap-2">
                                                <a href="{{ route('locations.contacts.edit', [$locationContact->location, $locationContact]) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Edit Relationship</a>
                                                <form method="POST" action="{{ route('locations.contacts.destroy', [$locationContact->location, $locationContact]) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="inline-flex items-center rounded-xl border border-red-300 px-3 py-1.5 text-xs font-medium text-red-700 transition hover:bg-red-50 dark:border-red-500/40 dark:text-red-300 dark:hover:bg-red-500/10">Remove From Location</button>
                                                </form>
                                            </div>
                                        @else
                                            <span class="text-gray-500 dark:text-gray-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr class="bg-white dark:bg-gray-800">
                                    <td colspan="5" class="px-5 py-8 text-center text-gray-500 dark:text-gray-400">This contact is not attached to any locations yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
