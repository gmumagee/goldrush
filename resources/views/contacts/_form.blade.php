<div class="grid gap-5 md:grid-cols-2">
    <div>
        <x-label for="first_name" value="First Name" />
        <x-input id="first_name" name="first_name" type="text" :value="old('first_name', $contact->first_name ?? '')" />
    </div>
    <div>
        <x-label for="last_name" value="Last Name" />
        <x-input id="last_name" name="last_name" type="text" :value="old('last_name', $contact->last_name ?? '')" />
    </div>
</div>

<div class="grid gap-5 md:grid-cols-2">
    <div>
        <x-label for="organization" value="Organization" />
        <x-input id="organization" name="organization" type="text" :value="old('organization', $contact->organization ?? '')" />
    </div>
    <div>
        <x-label for="title" value="Title" />
        <x-input id="title" name="title" type="text" :value="old('title', $contact->title ?? '')" />
    </div>
</div>

<div class="grid gap-5 md:grid-cols-3">
    <div>
        <x-label for="email" value="Email" />
        <x-input id="email" name="email" type="email" :value="old('email', $contact->email ?? '')" />
    </div>
    <div>
        <x-label for="phone" value="Phone" />
        <x-input id="phone" name="phone" type="text" :value="old('phone', $contact->phone ?? '')" />
    </div>
    <div>
        <x-label for="mobile_phone" value="Mobile Phone" />
        <x-input id="mobile_phone" name="mobile_phone" type="text" :value="old('mobile_phone', $contact->mobile_phone ?? '')" />
    </div>
</div>

<div>
    <x-label for="notes" value="Notes" />
    <textarea id="notes" name="notes" rows="4" class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-violet-500 focus:ring-violet-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100">{{ old('notes', $contact->notes ?? '') }}</textarea>
</div>
