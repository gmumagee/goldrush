<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Location;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Expense::class);

        $accountId = $this->currentAccountId($request);
        $filters = $this->validatedFilters($request, $accountId);

        $expenses = Expense::query()
            ->where('account_id', $accountId)
            ->with([
                'location' => fn ($query) => $query->where('account_id', $accountId),
                'createdBy',
            ])
            ->when($filters['location_filter'] === 'general', fn ($query) => $query->whereNull('location_id'))
            ->when($filters['location_id'] !== null, fn ($query) => $query->where('location_id', $filters['location_id']))
            ->when($filters['category'] !== '', fn ($query) => $query->where('category', $filters['category']))
            ->when($filters['date_from'] !== null, fn ($query) => $query->whereDate('expense_date', '>=', $filters['date_from']))
            ->when($filters['date_to'] !== null, fn ($query) => $query->whereDate('expense_date', '<=', $filters['date_to']))
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('expenses.index', [
            'expenses' => $expenses,
            'locations' => $this->locationsForAccount($accountId),
            'categoryOptions' => Expense::categoryOptions(),
            'filters' => [
                'location_filter' => $filters['location_filter'],
                'category' => $filters['category'],
                'date_from' => $request->input('date_from'),
                'date_to' => $request->input('date_to'),
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Expense::class);

        return view('expenses.create', [
            'locations' => $this->locationsForAccount($this->currentAccountId($request)),
            'categoryOptions' => Expense::categoryOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Expense::class);

        $accountId = $this->currentAccountId($request);
        $data = $this->validatedExpense($request, $accountId);

        $expense = new Expense($data);
        $expense->account_id = $accountId;
        $expense->created_by_user_id = $request->user()?->id;
        $expense->save();

        return redirect()
            ->route('expenses.index')
            ->with('status', 'Expense created successfully.');
    }

    public function edit(Request $request, int $expense): View
    {
        $accountId = $this->currentAccountId($request);
        $expense = $this->expenseForAccount($accountId, $expense);
        $this->authorize('update', $expense);

        return view('expenses.edit', [
            'expense' => $expense,
            'locations' => $this->locationsForAccount($accountId),
            'categoryOptions' => Expense::categoryOptions(),
        ]);
    }

    public function update(Request $request, int $expense): RedirectResponse
    {
        $accountId = $this->currentAccountId($request);
        $expense = $this->expenseForAccount($accountId, $expense);
        $this->authorize('update', $expense);

        $expense->update($this->validatedExpense($request, $accountId));

        return redirect()
            ->route('expenses.index')
            ->with('status', 'Expense updated successfully.');
    }

    public function destroy(Request $request, int $expense): RedirectResponse
    {
        $expense = $this->expenseForAccount($this->currentAccountId($request), $expense);
        $this->authorize('delete', $expense);

        $expense->delete();

        return redirect()
            ->route('expenses.index')
            ->with('status', 'Expense deleted successfully.');
    }

    protected function validatedExpense(Request $request, int $accountId): array
    {
        $data = $request->validate([
            'location_id' => [
                'nullable',
                'integer',
                Rule::exists('tbl_locations', 'id')->where(fn ($query) => $query->where('account_id', $accountId)),
            ],
            'category' => ['required', 'string', Rule::in(Expense::categories())],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'expense_date' => ['required', 'date_format:Y-m-d'],
            'vendor' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        return $data;
    }

    protected function validatedFilters(Request $request, int $accountId): array
    {
        $filters = $request->validate([
            'location_filter' => ['nullable', 'string'],
            'category' => ['nullable', 'string', Rule::in(Expense::categories())],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $locationFilter = trim((string) ($filters['location_filter'] ?? ''));

        if ($locationFilter !== '' && $locationFilter !== 'general') {
            if (! ctype_digit($locationFilter)) {
                throw ValidationException::withMessages([
                    'location_filter' => 'Choose a valid location filter.',
                ]);
            }

            $locationId = (int) $locationFilter;

            $locationExists = Location::query()
                ->where('account_id', $accountId)
                ->where('id', $locationId)
                ->exists();

            if (! $locationExists) {
                throw ValidationException::withMessages([
                    'location_filter' => 'Choose a valid location filter.',
                ]);
            }
        }

        $dateFrom = isset($filters['date_from']) && $filters['date_from'] !== ''
            ? (string) $filters['date_from']
            : null;
        $dateTo = isset($filters['date_to']) && $filters['date_to'] !== ''
            ? (string) $filters['date_to']
            : null;

        if ($dateFrom !== null && $dateTo !== null && $dateTo < $dateFrom) {
            throw ValidationException::withMessages([
                'date_to' => 'End date must be on or after the start date.',
            ]);
        }

        return [
            'location_filter' => $locationFilter,
            'location_id' => $locationFilter !== '' && $locationFilter !== 'general' ? (int) $locationFilter : null,
            'category' => trim((string) ($filters['category'] ?? '')),
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];
    }

    protected function expenseForAccount(int $accountId, int $expenseId): Expense
    {
        return Expense::query()
            ->where('account_id', $accountId)
            ->findOrFail($expenseId);
    }

    protected function locationsForAccount(int $accountId)
    {
        return Location::query()
            ->where('account_id', $accountId)
            ->notInventory()
            ->orderBy('location_name')
            ->get();
    }
}
