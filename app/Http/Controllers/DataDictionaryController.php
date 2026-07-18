<?php

namespace App\Http\Controllers;

use App\Models\AccountUser;
use App\Models\DataDictionary;
use App\Services\DataDictionaryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DataDictionaryController extends Controller
{
    public function __construct(protected DataDictionaryService $dataDictionaryService)
    {
        $this->middleware(function (Request $request, $next) {
            $this->ensureCanManageDataDictionary($request);

            return $next($request);
        });
    }

    public function index(Request $request): View
    {
        $accountId = $this->currentAccountId($request);
        $filters = $this->validatedIndexFilters($request);
        $names = $this->allowedNames($accountId);

        $entries = DataDictionary::query()
            ->forAccountScope($accountId)
            ->when($filters['name'] !== '', fn ($query) => $query->where('name', $filters['name']))
            ->when($filters['active_status'] === 'active', fn ($query) => $query->where('is_active', true))
            ->when($filters['active_status'] === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($filters['search'] !== '', function ($query) use ($filters) {
                $search = $filters['search'];

                $query->where(function ($dictionaryQuery) use ($search) {
                    $dictionaryQuery
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('value', 'like', '%'.$search.'%')
                        ->orWhere('label', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('name')
            ->orderByRaw('CASE WHEN account_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('sort_order')
            ->orderBy('label')
            ->orderBy('id')
            ->get();

        return view('data-dictionary.index', [
            'names' => $names,
            'entries' => $entries,
            'filters' => $filters,
            'protectedGroups' => DataDictionary::PROTECTED_GLOBAL_GROUPS,
        ]);
    }

    public function create(Request $request): View
    {
        $names = $this->allowedNames($this->currentAccountId($request));
        $selectedName = trim((string) $request->query('name', ''));

        return view('data-dictionary.create', [
            'names' => $names,
            'selectedName' => $names->contains($selectedName) ? $selectedName : '',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $accountId = $this->currentAccountId($request);
        $allowedNames = $this->allowedNames($accountId);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::in($allowedNames->all())],
            'value' => ['required', 'string', 'max:255'],
            'display_name' => ['required', 'string', 'max:255'],
        ]);

        $name = trim((string) $data['name']);
        $value = trim((string) $data['value']);
        $displayName = trim((string) $data['display_name']);

        if ($this->duplicateValueExists($accountId, $name, $value)) {
            throw ValidationException::withMessages([
                'value' => 'This value already exists for the selected name.',
            ]);
        }

        DataDictionary::create([
            'account_id' => $accountId,
            'name' => $name,
            'value' => $value,
            'label' => $displayName,
            'sort_order' => $this->nextSortOrder($accountId, $name),
            'is_active' => true,
        ]);

        return redirect()
            ->route('data-dictionary.index', ['name' => $name])
            ->with('status', 'Dictionary value added successfully.');
    }

    public function edit(Request $request, int $dataDictionary): View|RedirectResponse
    {
        $entry = $this->dictionaryEntryForScope($this->currentAccountId($request), $dataDictionary);

        if ($entry->isGlobal()) {
            return $this->globalValueRedirect();
        }

        return view('data-dictionary.edit', [
            'entry' => $entry,
            'protectedGroups' => DataDictionary::PROTECTED_GLOBAL_GROUPS,
        ]);
    }

    public function update(Request $request, int $dataDictionary): RedirectResponse
    {
        $entry = $this->dictionaryEntryForScope($this->currentAccountId($request), $dataDictionary);

        if ($entry->isGlobal()) {
            return $this->globalValueRedirect();
        }

        $data = $request->validate([
            'value' => ['required', 'string', 'max:255'],
            'display_name' => ['required', 'string', 'max:255'],
        ]);

        $value = trim((string) $data['value']);

        if ($this->duplicateValueExists((int) $entry->account_id, $entry->name, $value, $entry->id)) {
            throw ValidationException::withMessages([
                'value' => 'This value already exists for the selected name.',
            ]);
        }

        $entry->update([
            'value' => $value,
            'label' => trim((string) $data['display_name']),
        ]);

        return redirect()
            ->route('data-dictionary.index', ['name' => $entry->name])
            ->with('status', 'Dictionary value updated successfully.');
    }

    public function deactivate(Request $request, int $dataDictionary): RedirectResponse
    {
        $entry = $this->dictionaryEntryForScope($this->currentAccountId($request), $dataDictionary);

        if ($entry->isGlobal()) {
            return $this->globalValueRedirect();
        }

        $entry->update(['is_active' => false]);

        return redirect()
            ->route('data-dictionary.index', ['name' => $entry->name])
            ->with('status', 'Dictionary value deactivated successfully.');
    }

    public function activate(Request $request, int $dataDictionary): RedirectResponse
    {
        $entry = $this->dictionaryEntryForScope($this->currentAccountId($request), $dataDictionary);

        if ($entry->isGlobal()) {
            return $this->globalValueRedirect();
        }

        $entry->update(['is_active' => true]);

        return redirect()
            ->route('data-dictionary.index', ['name' => $entry->name])
            ->with('status', 'Dictionary value reactivated successfully.');
    }

    protected function ensureCanManageDataDictionary(Request $request): void
    {
        $membership = AccountUser::query()
            ->where('account_id', $this->currentAccountId($request))
            ->where('user_id', $request->user()->id)
            ->where('status', AccountUser::STATUS_ACTIVE)
            ->first();

        if (! $membership || ! $membership->canManageAccountUsers()) {
            abort(403);
        }
    }

    protected function dictionaryEntryForScope(int $accountId, int $id): DataDictionary
    {
        return DataDictionary::query()
            ->forAccountScope($accountId)
            ->findOrFail($id);
    }

    protected function validatedIndexFilters(Request $request): array
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:100'],
            'active_status' => ['nullable', 'string', Rule::in(['active', 'inactive', 'all'])],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        return [
            'name' => trim((string) ($data['name'] ?? '')),
            'active_status' => (string) ($data['active_status'] ?? 'active'),
            'search' => trim((string) ($data['search'] ?? '')),
        ];
    }

    protected function allowedNames(int $accountId): \Illuminate\Support\Collection
    {
        return $this->dataDictionaryService->groups($accountId)->values();
    }

    protected function duplicateValueExists(int $accountId, string $name, string $value, ?int $ignoreId = null): bool
    {
        return DataDictionary::query()
            ->forAccountScope($accountId)
            ->where('name', $name)
            ->whereRaw('LOWER(value) = ?', [mb_strtolower($value)])
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();
    }

    protected function nextSortOrder(int $accountId, string $name): int
    {
        $maxSortOrder = DataDictionary::query()
            ->forAccountScope($accountId)
            ->where('name', $name)
            ->max('sort_order');

        return $maxSortOrder === null ? 10 : ((int) $maxSortOrder + 10);
    }

    protected function globalValueRedirect(): RedirectResponse
    {
        return redirect()
            ->route('data-dictionary.index')
            ->withErrors(['data_dictionary' => 'Global dictionary values cannot be edited here.']);
    }
}
