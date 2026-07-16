<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function index(Request $request): View
    {
        $accountId = $this->currentAccountId($request);
        $search = trim((string) $request->string('search'));

        $contacts = Contact::query()
            ->where('account_id', $accountId)
            ->withCount('locationContacts')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($contactQuery) use ($search) {
                    $contactQuery
                        ->where('first_name', 'like', '%'.$search.'%')
                        ->orWhere('last_name', 'like', '%'.$search.'%')
                        ->orWhere('organization', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%')
                        ->orWhere('mobile_phone', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        return view('contacts.index', compact('contacts', 'search'));
    }

    public function create(): View
    {
        return view('contacts.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedContactData($request);
        $data['account_id'] = $this->currentAccountId($request);

        $contact = Contact::create($data);

        return redirect()
            ->route('contacts.show', $contact)
            ->with('status', 'Contact created successfully.');
    }

    public function show(Request $request, int $contact): View
    {
        $contact = $this->contactForAccount($this->currentAccountId($request), $contact, [
            'locationContacts.location',
        ]);

        return view('contacts.show', compact('contact'));
    }

    public function edit(Request $request, int $contact): View
    {
        $contact = $this->contactForAccount($this->currentAccountId($request), $contact);

        return view('contacts.edit', compact('contact'));
    }

    public function update(Request $request, int $contact): RedirectResponse
    {
        $contact = $this->contactForAccount($this->currentAccountId($request), $contact);
        $contact->update($this->validatedContactData($request));

        return redirect()
            ->route('contacts.show', $contact)
            ->with('status', 'Contact updated successfully.');
    }

    public function destroy(Request $request, int $contact): RedirectResponse
    {
        $contact = $this->contactForAccount($this->currentAccountId($request), $contact, ['locationContacts']);

        if ($contact->locationContacts()->exists()) {
            return back()->withErrors([
                'contact' => 'This contact cannot be deleted because it is attached to one or more locations.',
            ]);
        }

        $contact->delete();

        return redirect()
            ->route('contacts.index')
            ->with('status', 'Contact deleted successfully.');
    }

    protected function contactForAccount(int $accountId, int $contactId, array $with = []): Contact
    {
        return Contact::query()
            ->where('account_id', $accountId)
            ->with($with)
            ->findOrFail($contactId);
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
            'notes' => ['nullable', 'string'],
        ]);

        // Prevent empty placeholder contacts that cannot be identified later.
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

        return $data;
    }
}
