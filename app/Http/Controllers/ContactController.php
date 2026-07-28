<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Support\EntityValidation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Contact::class);

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
        $this->authorize('create', Contact::class);

        return view('contacts.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Contact::class);

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
        $this->authorize('view', $contact);

        return view('contacts.show', compact('contact'));
    }

    public function edit(Request $request, int $contact): View
    {
        $contact = $this->contactForAccount($this->currentAccountId($request), $contact);
        $this->authorize('update', $contact);

        return view('contacts.edit', compact('contact'));
    }

    public function update(Request $request, int $contact): RedirectResponse
    {
        $contact = $this->contactForAccount($this->currentAccountId($request), $contact);
        $this->authorize('update', $contact);
        $contact->update($this->validatedContactData($request));

        return redirect()
            ->route('contacts.show', $contact)
            ->with('status', 'Contact updated successfully.');
    }

    public function destroy(Request $request, int $contact): RedirectResponse
    {
        $contact = $this->contactForAccount($this->currentAccountId($request), $contact, ['locationContacts']);
        $this->authorize('delete', $contact);

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
        $data = $request->validate(EntityValidation::contactRules());
        EntityValidation::ensureContactHasIdentity($data);

        return $data;
    }
}
