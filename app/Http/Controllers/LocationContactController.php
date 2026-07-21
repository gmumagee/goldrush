<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\DataDictionary;
use App\Models\Location;
use App\Models\LocationContact;
use App\Services\DataDictionaryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LocationContactController extends Controller
{
    public function __construct(protected DataDictionaryService $dataDictionaryService)
    {
    }

    public function create(Request $request, int $location): View
    {
        $accountId = $this->currentAccountId($request);
        $location = $this->locationForAccount($accountId, $location);
        $this->authorize('update', $location);

        $contacts = Contact::query()
            ->where('account_id', $accountId)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('organization')
            ->get();

        return view('location-contacts.create', [
            'location' => $location,
            'contacts' => $contacts,
            'roleOptions' => $this->dataDictionaryService->options(DataDictionary::GROUP_LOCATION_CONTACT_ROLE, $accountId),
        ]);
    }

    public function store(Request $request, int $location): RedirectResponse
    {
        $accountId = $this->currentAccountId($request);
        $location = $this->locationForAccount($accountId, $location);
        $this->authorize('update', $location);
        $mode = (string) $request->input('mode', 'existing');

        if ($mode === 'new') {
            return $this->storeNewContact($request, $location, $accountId);
        }

        return $this->attachExistingContact($request, $location, $accountId);
    }

    public function edit(Request $request, int $location, int $locationContact): View
    {
        $accountId = $this->currentAccountId($request);
        $location = $this->locationForAccount($accountId, $location);
        $locationContact = $this->locationContactForAccount($accountId, $location->id, $locationContact, ['contact']);
        $this->authorize('update', $locationContact);

        return view('location-contacts.edit', [
            'location' => $location,
            'locationContact' => $locationContact,
            'roleOptions' => $this->dataDictionaryService->options(DataDictionary::GROUP_LOCATION_CONTACT_ROLE, $accountId),
        ]);
    }

    public function update(Request $request, int $location, int $locationContact): RedirectResponse
    {
        $accountId = $this->currentAccountId($request);
        $location = $this->locationForAccount($accountId, $location);
        $locationContact = $this->locationContactForAccount($accountId, $location->id, $locationContact);
        $this->authorize('update', $locationContact);
        $relationshipData = $this->validatedRelationshipData($request, $accountId);

        DB::transaction(function () use ($relationshipData, $location, $locationContact) {
            $this->guardDuplicateRelationship(
                $location->account_id,
                $location->id,
                $locationContact->contact_id,
                $relationshipData['contact_role'] ?? null,
                $locationContact->id
            );

            if ($relationshipData['is_primary']) {
                $this->clearPrimaryFlag((int) $location->account_id, $location->id, $locationContact->id);
            }

            $locationContact->update($relationshipData);
        });

        return redirect()
            ->route('locations.show', $location)
            ->with('status', 'Location contact updated successfully.');
    }

    public function destroy(Request $request, int $location, int $locationContact): RedirectResponse
    {
        $accountId = $this->currentAccountId($request);
        $location = $this->locationForAccount($accountId, $location);
        $locationContact = $this->locationContactForAccount($accountId, $location->id, $locationContact);
        $this->authorize('delete', $locationContact);
        $locationContact->delete();

        return redirect()
            ->route('locations.show', $location)
            ->with('status', 'Contact removed from location successfully.');
    }

    protected function attachExistingContact(Request $request, Location $location, int $accountId): RedirectResponse
    {
        $data = $request->validate([
            'contact_id' => ['required', 'integer'],
        ]);

        $relationshipData = $this->validatedRelationshipData($request, $accountId);
        $contact = Contact::query()
            ->where('account_id', $accountId)
            ->findOrFail((int) $data['contact_id']);

        DB::transaction(function () use ($location, $contact, $relationshipData, $accountId) {
            $this->guardDuplicateRelationship(
                $accountId,
                $location->id,
                $contact->id,
                $relationshipData['contact_role'] ?? null
            );

            if ($relationshipData['is_primary']) {
                $this->clearPrimaryFlag($accountId, $location->id);
            }

            LocationContact::create([
                'account_id' => $accountId,
                'location_id' => $location->id,
                'contact_id' => $contact->id,
                'contact_role' => $relationshipData['contact_role'] ?? null,
                'is_primary' => $relationshipData['is_primary'],
                'notes' => $relationshipData['notes'] ?? null,
            ]);
        });

        return redirect()
            ->route('locations.show', $location)
            ->with('status', 'Contact attached to location successfully.');
    }

    protected function storeNewContact(Request $request, Location $location, int $accountId): RedirectResponse
    {
        $contactData = $this->validatedContactData($request);
        $relationshipData = $this->validatedRelationshipData($request, $accountId);

        DB::transaction(function () use ($contactData, $relationshipData, $location, $accountId) {
            $contact = Contact::create([
                ...$contactData,
                'account_id' => $accountId,
            ]);

            $this->guardDuplicateRelationship(
                $accountId,
                $location->id,
                $contact->id,
                $relationshipData['contact_role'] ?? null
            );

            if ($relationshipData['is_primary']) {
                $this->clearPrimaryFlag($accountId, $location->id);
            }

            LocationContact::create([
                'account_id' => $accountId,
                'location_id' => $location->id,
                'contact_id' => $contact->id,
                'contact_role' => $relationshipData['contact_role'] ?? null,
                'is_primary' => $relationshipData['is_primary'],
                'notes' => $relationshipData['notes'] ?? null,
            ]);
        });

        return redirect()
            ->route('locations.show', $location)
            ->with('status', 'Contact attached to location successfully.');
    }

    protected function locationForAccount(int $accountId, int $locationId): Location
    {
        return Location::query()
            ->where('account_id', $accountId)
            ->findOrFail($locationId);
    }

    protected function locationContactForAccount(int $accountId, int $locationId, int $locationContactId, array $with = []): LocationContact
    {
        return LocationContact::query()
            ->where('account_id', $accountId)
            ->where('location_id', $locationId)
            ->with($with)
            ->findOrFail($locationContactId);
    }

    protected function validatedRelationshipData(Request $request, int $accountId): array
    {
        $data = $request->validate([
            'contact_role' => ['nullable', 'string', $this->activeDictionaryValueRule(DataDictionary::GROUP_LOCATION_CONTACT_ROLE, $accountId)],
            'is_primary' => ['nullable', 'boolean'],
            'relationship_notes' => ['nullable', 'string'],
        ]);

        return [
            'contact_role' => $data['contact_role'] ?: null,
            'is_primary' => $request->boolean('is_primary'),
            'notes' => $data['relationship_notes'] ?? null,
        ];
    }

    protected function validatedContactData(Request $request): array
    {
        $data = $request->validate([
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'organization' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'mobile_phone' => ['nullable', 'string', 'max:50'],
            'contact_notes' => ['nullable', 'string'],
        ]);

        // New reusable contacts still need at least one identifying field.
        if (
            trim((string) ($data['first_name'] ?? '')) === ''
            && trim((string) ($data['last_name'] ?? '')) === ''
            && trim((string) ($data['organization'] ?? '')) === ''
            && trim((string) ($data['email'] ?? '')) === ''
            && trim((string) ($data['phone'] ?? '')) === ''
            && trim((string) ($data['mobile_phone'] ?? '')) === ''
        ) {
            throw ValidationException::withMessages([
                'first_name' => 'Enter at least a name, organization, email, or phone number.',
            ]);
        }

        return [
            'first_name' => $data['first_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'organization' => $data['organization'] ?? null,
            'title' => $data['title'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'mobile_phone' => $data['mobile_phone'] ?? null,
            'notes' => $data['contact_notes'] ?? null,
        ];
    }

    protected function guardDuplicateRelationship(
        int $accountId,
        int $locationId,
        int $contactId,
        ?string $contactRole,
        ?int $ignoreId = null,
    ): void {
        $query = LocationContact::query()
            ->where('account_id', $accountId)
            ->where('location_id', $locationId)
            ->where('contact_id', $contactId);

        if ($contactRole === null) {
            $query->whereNull('contact_role');
        } else {
            $query->where('contact_role', $contactRole);
        }

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'contact_id' => 'This contact is already attached to this location with that role.',
            ]);
        }
    }

    protected function clearPrimaryFlag(int $accountId, int $locationId, ?int $exceptId = null): void
    {
        $query = LocationContact::query()
            ->where('account_id', $accountId)
            ->where('location_id', $locationId);

        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        $query->update(['is_primary' => false]);
    }
}
