<x-app-layout title="Add Location Contact">
    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-5xl space-y-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 md:text-3xl">Manage Contacts for {{ $location->location_name }}</h1>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Attach an existing contact or create a new reusable contact for this location.</p>
                </div>
                <a href="{{ route('locations.show', $location) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Back to Location</a>
            </div>

            <x-validation-errors />

            <section id="attach-existing" class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Attach Existing Contact</h2>
                    </div>
                </div>
                <div class="panel-body">
                    <form method="POST" action="{{ route('locations.contacts.store', $location) }}" class="space-y-5">
                        @csrf
                        <input type="hidden" name="mode" value="existing">

                        <div>
                            <x-label for="contact_id" value="Existing Contact" />
                            <select id="contact_id" name="contact_id" class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100" required>
                                <option value="">Select a contact</option>
                                @foreach ($contacts as $contact)
                                    <option value="{{ $contact->id }}" @selected((string) old('contact_id') === (string) $contact->id)>{{ $contact->display_name }}{{ $contact->organization ? ' - '.$contact->organization : '' }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="grid gap-5 md:grid-cols-3">
                            <div>
                                <x-label for="existing_contact_role" value="Contact Role" />
                                <select id="existing_contact_role" name="contact_role" class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100">
                                    <option value="">No role</option>
                                    @foreach ($roleOptions as $role)
                                        <option value="{{ $role->value }}" @selected(old('contact_role') === $role->value)>{{ $role->displayLabel() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="flex items-end">
                                <label class="inline-flex items-center gap-3 rounded-xl border border-gray-200 px-4 py-3 text-sm text-gray-700 dark:border-gray-700/60 dark:text-gray-200">
                                    <input type="checkbox" name="is_primary" value="1" @checked(old('is_primary')) class="rounded border-gray-300 text-violet-600 focus:ring-violet-500">
                                    <span>Primary Contact</span>
                                </label>
                            </div>
                        </div>

                        <div>
                            <x-label for="existing_relationship_notes" value="Relationship Notes" />
                            <textarea id="existing_relationship_notes" name="relationship_notes" rows="3" class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100">{{ old('relationship_notes') }}</textarea>
                        </div>

                        <div class="flex items-center justify-end gap-3">
                            <a href="{{ route('locations.show', $location) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Cancel</a>
                            <x-button>Attach Existing Contact</x-button>
                        </div>
                    </form>
                </div>
            </section>

            <section id="create-new" class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Create New Contact and Attach</h2>
                    </div>
                </div>
                <div class="panel-body">
                    <form method="POST" action="{{ route('locations.contacts.store', $location) }}" class="space-y-5">
                        @csrf
                        <input type="hidden" name="mode" value="new">

                        <div class="grid gap-5 md:grid-cols-2">
                            <div>
                                <x-label for="first_name" value="First Name" />
                                <x-input id="first_name" name="first_name" type="text" :value="old('first_name')" />
                            </div>
                            <div>
                                <x-label for="last_name" value="Last Name" />
                                <x-input id="last_name" name="last_name" type="text" :value="old('last_name')" />
                            </div>
                        </div>

                        <div class="grid gap-5 md:grid-cols-2">
                            <div>
                                <x-label for="organization" value="Organization" />
                                <x-input id="organization" name="organization" type="text" :value="old('organization')" />
                            </div>
                            <div>
                                <x-label for="title" value="Title" />
                                <x-input id="title" name="title" type="text" :value="old('title')" />
                            </div>
                        </div>

                        <div class="grid gap-5 md:grid-cols-3">
                            <div>
                                <x-label for="email" value="Email" />
                                <x-input id="email" name="email" type="email" :value="old('email')" />
                            </div>
                            <div>
                                <x-label for="phone" value="Phone" />
                                <x-input id="phone" name="phone" type="text" :value="old('phone')" />
                            </div>
                            <div>
                                <x-label for="mobile_phone" value="Mobile Phone" />
                                <x-input id="mobile_phone" name="mobile_phone" type="text" :value="old('mobile_phone')" />
                            </div>
                        </div>

                        <div>
                            <x-label for="contact_notes" value="Contact Notes" />
                            <textarea id="contact_notes" name="contact_notes" rows="3" class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100">{{ old('contact_notes') }}</textarea>
                        </div>

                        <div class="grid gap-5 md:grid-cols-3">
                            <div>
                                <x-label for="new_contact_role" value="Contact Role" />
                                <select id="new_contact_role" name="contact_role" class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100">
                                    <option value="">No role</option>
                                    @foreach ($roleOptions as $role)
                                        <option value="{{ $role->value }}" @selected(old('contact_role') === $role->value)>{{ $role->displayLabel() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="flex items-end">
                                <label class="inline-flex items-center gap-3 rounded-xl border border-gray-200 px-4 py-3 text-sm text-gray-700 dark:border-gray-700/60 dark:text-gray-200">
                                    <input type="checkbox" name="is_primary" value="1" @checked(old('is_primary')) class="rounded border-gray-300 text-violet-600 focus:ring-violet-500">
                                    <span>Primary Contact</span>
                                </label>
                            </div>
                        </div>

                        <div>
                            <x-label for="new_relationship_notes" value="Relationship Notes" />
                            <textarea id="new_relationship_notes" name="relationship_notes" rows="3" class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100">{{ old('relationship_notes') }}</textarea>
                        </div>

                        <div class="flex items-center justify-end gap-3">
                            <a href="{{ route('locations.show', $location) }}" class="inline-flex items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Cancel</a>
                            <x-button>Create and Attach Contact</x-button>
                        </div>
                    </form>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
