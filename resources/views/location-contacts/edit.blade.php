<x-app-layout title="Edit Location Contact">
    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-3xl space-y-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 md:text-3xl">Edit Location Contact</h1>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Update this contact’s role and primary flag for {{ $location->location_name }}.</p>
                </div>
                <a href="{{ route('locations.show', $location) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Back to Location</a>
            </div>

            <section class="panel">
                <div class="panel-body space-y-6">
                    <dl class="grid gap-4 rounded-2xl border border-gray-200 p-5 text-sm dark:border-gray-700/60 md:grid-cols-2">
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Contact</dt>
                            <dd class="mt-1 font-medium text-gray-800 dark:text-gray-100">{{ $locationContact->contact?->display_name ?? 'Unknown Contact' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Reusable Contact</dt>
                            <dd class="mt-1">
                                @if ($locationContact->contact)
                                    <a href="{{ route('contacts.edit', $locationContact->contact) }}" class="text-sm font-medium text-violet-600 hover:text-violet-500 dark:text-violet-300 dark:hover:text-violet-200">Edit Contact Details</a>
                                @else
                                    <span class="text-gray-500 dark:text-gray-400">Unavailable</span>
                                @endif
                            </dd>
                        </div>
                    </dl>

                    <form method="POST" action="{{ route('locations.contacts.update', [$location, $locationContact]) }}" class="space-y-5">
                        @csrf
                        @method('PUT')

                        <div>
                            <x-label for="contact_role" value="Contact Role" />
                            <select id="contact_role" name="contact_role" class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100">
                                <option value="">No role</option>
                                @foreach ($roleOptions as $role)
                                    <option value="{{ $role->value }}" @selected(old('contact_role', $locationContact->contact_role) === $role->value)>{{ $role->displayLabel() }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="inline-flex items-center gap-3 rounded-xl border border-gray-200 px-4 py-3 text-sm text-gray-700 dark:border-gray-700/60 dark:text-gray-200">
                                <input type="checkbox" name="is_primary" value="1" @checked(old('is_primary', $locationContact->is_primary)) class="rounded border-gray-300 text-violet-600 focus:ring-violet-500">
                                <span>Primary Contact</span>
                            </label>
                        </div>

                        <div>
                            <x-label for="relationship_notes" value="Relationship Notes" />
                            <textarea id="relationship_notes" name="relationship_notes" rows="4" class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100">{{ old('relationship_notes', $locationContact->notes) }}</textarea>
                        </div>

                        <div class="flex items-center justify-end gap-3">
                            <a href="{{ route('locations.show', $location) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Cancel</a>
                            <x-button>Save Relationship</x-button>
                        </div>
                    </form>

                    <x-validation-errors />
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
