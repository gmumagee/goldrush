<?php

namespace App\Http\Controllers;

use App\Models\AccountUser;
use App\Models\DataDictionary;
use App\Models\Location;
use App\Models\LocationDocument;
use App\Services\DataDictionaryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class LocationDocumentController extends Controller
{
    public function __construct(protected DataDictionaryService $dataDictionaryService)
    {
    }

    public function create(Request $request, int $location): View
    {
        $this->ensureCanManageDocuments($request);

        $accountId = $this->currentAccountId($request);
        $location = $this->locationForAccount($accountId, $location);

        return view('location-documents.create', [
            'location' => $location,
            'documentTypeOptions' => $this->dataDictionaryService->options(DataDictionary::GROUP_LOCATION_DOCUMENT_TYPE, $accountId),
        ]);
    }

    public function store(Request $request, int $location): RedirectResponse
    {
        $this->ensureCanManageDocuments($request);

        $accountId = $this->currentAccountId($request);
        $location = $this->locationForAccount($accountId, $location);
        $data = $this->validatedData($request, $accountId);
        $uploadedFile = $request->file('file');
        $extension = mb_strtolower((string) ($uploadedFile->getClientOriginalExtension() ?: $uploadedFile->extension()));
        $storedFilename = now()->format('YmdHis').'_'.Str::uuid().($extension !== '' ? '.'.$extension : '');
        $storagePath = $uploadedFile->storeAs(
            'location-documents/'.$accountId.'/'.$location->id,
            $storedFilename,
            'private'
        );

        if (! is_string($storagePath) || $storagePath === '') {
            return back()->withErrors([
                'file' => 'The document could not be stored. Please try again.',
            ])->withInput();
        }

        try {
            LocationDocument::create([
                'account_id' => $accountId,
                'location_id' => $location->id,
                'document_type' => $data['document_type'] ?? null,
                'title' => $data['title'] ?? null,
                'description' => $data['description'] ?? null,
                'original_filename' => $uploadedFile->getClientOriginalName(),
                'stored_filename' => $storedFilename,
                'storage_disk' => 'private',
                'storage_path' => $storagePath,
                'mime_type' => $uploadedFile->getMimeType(),
                'file_size' => $uploadedFile->getSize(),
                'uploaded_by_user_id' => $request->user()->id,
            ]);
        } catch (Throwable $exception) {
            Storage::disk('private')->delete($storagePath);

            throw $exception;
        }

        return redirect()
            ->route('locations.show', $location)
            ->with('status', 'Document uploaded successfully.');
    }

    public function show(Request $request, int $location, int $document): View
    {
        $membership = $this->currentMembership($request);
        $location = $this->locationForAccount($membership->account_id, $location);
        $document = $this->documentForAccount($membership->account_id, $location->id, $document, ['uploadedBy']);

        return view('location-documents.show', [
            'location' => $location,
            'document' => $document,
            'documentTypeLabels' => $this->dataDictionaryService->labels(DataDictionary::GROUP_LOCATION_DOCUMENT_TYPE, $membership->account_id, true),
            'canManageDocuments' => $this->canManageDocuments($membership),
        ]);
    }

    public function download(Request $request, int $location, int $document): StreamedResponse|RedirectResponse
    {
        $membership = $this->currentMembership($request);
        $location = $this->locationForAccount($membership->account_id, $location);
        $document = $this->documentForAccount($membership->account_id, $location->id, $document);

        if (! Storage::disk($document->storage_disk)->exists($document->storage_path)) {
            return redirect()
                ->route('locations.show', $location)
                ->withErrors(['document' => 'Document file was not found.']);
        }

        return Storage::disk($document->storage_disk)->download(
            $document->storage_path,
            $document->original_filename
        );
    }

    public function destroy(Request $request, int $location, int $document): RedirectResponse
    {
        $this->ensureCanManageDocuments($request);

        $accountId = $this->currentAccountId($request);
        $location = $this->locationForAccount($accountId, $location);
        $document = $this->documentForAccount($accountId, $location->id, $document);

        DB::transaction(function () use ($document) {
            $document->deleteStoredFile();
            $document->delete();
        });

        return redirect()
            ->route('locations.show', $location)
            ->with('status', 'Document deleted successfully.');
    }

    protected function validatedData(Request $request, int $accountId): array
    {
        return $request->validate([
            'document_type' => ['nullable', 'string', $this->activeDictionaryValueRule(DataDictionary::GROUP_LOCATION_DOCUMENT_TYPE, $accountId)],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'file' => [
                'required',
                'file',
                'max:10240',
                'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,txt',
            ],
        ]);
    }

    protected function locationForAccount(int $accountId, int $locationId): Location
    {
        return Location::query()
            ->where('account_id', $accountId)
            ->findOrFail($locationId);
    }

    protected function documentForAccount(int $accountId, int $locationId, int $documentId, array $with = []): LocationDocument
    {
        return LocationDocument::query()
            ->where('account_id', $accountId)
            ->where('location_id', $locationId)
            ->with($with)
            ->findOrFail($documentId);
    }

    protected function currentMembership(Request $request): AccountUser
    {
        $membership = AccountUser::query()
            ->where('account_id', $this->currentAccountId($request))
            ->where('user_id', $request->user()->id)
            ->where('status', AccountUser::STATUS_ACTIVE)
            ->first();

        abort_if(! $membership, 403);

        return $membership;
    }

    protected function ensureCanManageDocuments(Request $request): void
    {
        $membership = $this->currentMembership($request);

        if (! $this->canManageDocuments($membership)) {
            abort(403);
        }
    }

    protected function canManageDocuments(AccountUser $membership): bool
    {
        return $membership->roleMatches(AccountUser::ROLE_OWNER)
            || $membership->roleMatches(AccountUser::ROLE_ADMIN)
            || $membership->roleMatches(AccountUser::ROLE_MANAGER);
    }
}
