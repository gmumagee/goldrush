<?php

namespace App\Http\Controllers;

use App\Models\AccountUser;
use App\Models\DataDictionary;
use App\Models\User;
use App\Services\DataDictionaryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AccountUserController extends Controller
{
    public function __construct(protected DataDictionaryService $dataDictionaryService)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', AccountUser::class);

        $accountId = $this->currentAccountId($request);
        $search = trim((string) $request->string('search'));

        $memberships = AccountUser::query()
            ->where('account_id', $accountId)
            ->with('user')
            ->when($search !== '', function ($query) use ($search) {
                $query->whereHas('user', function ($userQuery) use ($search) {
                    $userQuery
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->orderByRaw("CASE WHEN LOWER(role) = 'owner' THEN 0 WHEN LOWER(role) = 'admin' THEN 1 ELSE 2 END")
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        return view('account-users.index', [
            'memberships' => $memberships,
            'search' => $search,
            'roleLabels' => $this->dataDictionaryService->labels(DataDictionary::GROUP_ACCOUNT_USER_ROLE, $accountId, true),
            'membershipStatusLabels' => $this->dataDictionaryService->labels(DataDictionary::GROUP_ACCOUNT_USER_STATUS, $accountId, true),
            'userStatusLabels' => $this->dataDictionaryService->labels(DataDictionary::GROUP_USER_STATUS, $accountId, true),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', AccountUser::class);

        $accountId = $this->currentAccountId($request);

        return view('account-users.create', [
            'roleOptions' => $this->dataDictionaryService->options(DataDictionary::GROUP_ACCOUNT_USER_ROLE, $accountId),
            'membershipStatusOptions' => $this->dataDictionaryService->options(DataDictionary::GROUP_ACCOUNT_USER_STATUS, $accountId),
            'userStatusOptions' => $this->dataDictionaryService->options(DataDictionary::GROUP_USER_STATUS, $accountId),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', AccountUser::class);

        $accountId = $this->currentAccountId($request);
        $email = mb_strtolower(trim((string) $request->input('email')));
        $existingUser = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', 'string', $this->activeDictionaryValueRule(DataDictionary::GROUP_ACCOUNT_USER_ROLE, $accountId)],
            'status' => ['required', 'string', $this->activeDictionaryValueRule(DataDictionary::GROUP_ACCOUNT_USER_STATUS, $accountId)],
            'user_status' => ['required', 'string', $this->activeDictionaryValueRule(DataDictionary::GROUP_USER_STATUS, $accountId)],
            'password' => [$existingUser ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
        ]);

        $data['email'] = $email;

        DB::transaction(function () use ($accountId, $data, $existingUser) {
            if ($existingUser) {
                $alreadyMember = AccountUser::query()
                    ->where('account_id', $accountId)
                    ->where('user_id', $existingUser->id)
                    ->exists();

                if ($alreadyMember) {
                    throw ValidationException::withMessages([
                        'email' => 'This user already belongs to this account.',
                    ]);
                }

                AccountUser::create([
                    'account_id' => $accountId,
                    'user_id' => $existingUser->id,
                    'role' => $data['role'],
                    'status' => $data['status'],
                ]);

                return;
            }

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'status' => $data['user_status'],
            ]);

            AccountUser::create([
                'account_id' => $accountId,
                'user_id' => $user->id,
                'role' => $data['role'],
                'status' => $data['status'],
            ]);
        });

        return redirect()
            ->route('account-users.index')
            ->with('status', 'User added to account successfully.');
    }

    public function edit(Request $request, int $accountUser): View
    {
        $accountId = $this->currentAccountId($request);
        $membership = $this->membershipForAccount($accountId, $accountUser, ['user']);
        $this->authorize('update', $membership);

        return view('account-users.edit', [
            'membership' => $membership,
            'roleOptions' => $this->dataDictionaryService->options(DataDictionary::GROUP_ACCOUNT_USER_ROLE, $accountId),
            'membershipStatusOptions' => $this->dataDictionaryService->options(DataDictionary::GROUP_ACCOUNT_USER_STATUS, $accountId),
            'roleLabels' => $this->dataDictionaryService->labels(DataDictionary::GROUP_ACCOUNT_USER_ROLE, $accountId, true),
            'membershipStatusLabels' => $this->dataDictionaryService->labels(DataDictionary::GROUP_ACCOUNT_USER_STATUS, $accountId, true),
            'userStatusLabels' => $this->dataDictionaryService->labels(DataDictionary::GROUP_USER_STATUS, $accountId, true),
        ]);
    }

    public function update(Request $request, int $accountUser): RedirectResponse
    {
        $accountId = $this->currentAccountId($request);
        $membership = $this->membershipForAccount($accountId, $accountUser);
        $this->authorize('update', $membership);

        $data = $request->validate([
            'role' => ['required', 'string', $this->activeDictionaryValueRule(DataDictionary::GROUP_ACCOUNT_USER_ROLE, $accountId)],
            'status' => ['required', 'string', $this->activeDictionaryValueRule(DataDictionary::GROUP_ACCOUNT_USER_STATUS, $accountId)],
        ]);

        $this->guardLastOwner($membership, $data['role'], $data['status']);

        $membership->update([
            'role' => $data['role'],
            'status' => $data['status'],
        ]);

        if ((int) $membership->user_id === (int) $request->user()->id) {
            if (! $membership->isActive()) {
                $request->session()->forget('current_account_id');

                return redirect()
                    ->route('accounts.select')
                    ->with('status', 'Your access to that account has been updated.');
            }

            if (! $membership->canManageAccountUsers()) {
                return redirect()
                    ->route('dashboard')
                    ->with('status', 'User role updated successfully.');
            }
        }

        return redirect()
            ->route('account-users.index')
            ->with('status', 'User role updated successfully.');
    }

    public function deactivate(Request $request, int $accountUser): RedirectResponse
    {
        $membership = $this->membershipForAccount($this->currentAccountId($request), $accountUser);
        $this->authorize('update', $membership);
        $this->guardLastOwner($membership, $membership->role, AccountUser::STATUS_INACTIVE);

        $membership->update([
            'status' => AccountUser::STATUS_INACTIVE,
        ]);

        if ((int) $membership->user_id === (int) $request->user()->id) {
            $request->session()->forget('current_account_id');

            return redirect()
                ->route('accounts.select')
                ->with('status', 'Your access to that account has been deactivated.');
        }

        return redirect()
            ->route('account-users.index')
            ->with('status', 'User deactivated successfully.');
    }

    public function destroy(Request $request, int $accountUser): RedirectResponse
    {
        $membership = $this->membershipForAccount($this->currentAccountId($request), $accountUser);
        $this->authorize('delete', $membership);
        $this->guardLastOwner($membership, null, null, true);

        $removingSelf = (int) $membership->user_id === (int) $request->user()->id;
        $membership->delete();

        if ($removingSelf) {
            $request->session()->forget('current_account_id');

            return redirect()
                ->route('accounts.select')
                ->with('status', 'Your account membership was removed successfully.');
        }

        return redirect()
            ->route('account-users.index')
            ->with('status', 'User removed from account successfully.');
    }
    protected function membershipForAccount(int $accountId, int $accountUserId, array $with = []): AccountUser
    {
        return AccountUser::query()
            ->where('account_id', $accountId)
            ->with($with)
            ->findOrFail($accountUserId);
    }

    protected function guardLastOwner(
        AccountUser $membership,
        ?string $newRole = null,
        ?string $newStatus = null,
        bool $deleting = false,
    ): void {
        if (! $membership->isOwner() || ! $membership->isActive()) {
            return;
        }

        $willStillBeActiveOwner = ! $deleting
            && strcasecmp(trim((string) ($newRole ?? $membership->role)), AccountUser::ROLE_OWNER) === 0
            && strcasecmp(trim((string) ($newStatus ?? $membership->status)), AccountUser::STATUS_ACTIVE) === 0;

        if ($willStillBeActiveOwner) {
            return;
        }

        $otherActiveOwners = AccountUser::query()
            ->where('account_id', $membership->account_id)
            ->where('id', '!=', $membership->id)
            ->whereRaw('LOWER(role) = ?', [mb_strtolower(AccountUser::ROLE_OWNER)])
            ->whereRaw('LOWER(status) = ?', [mb_strtolower(AccountUser::STATUS_ACTIVE)])
            ->count();

        if ($otherActiveOwners === 0) {
            throw ValidationException::withMessages([
                'account_user' => 'This account must have at least one active owner.',
            ]);
        }
    }
}
