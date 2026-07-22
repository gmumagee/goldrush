<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountSelectionController extends Controller
{
    public function edit(Request $request): View|RedirectResponse
    {
        if (Tenancy::isSingle()) {
            Tenancy::pinSingleAccountInSession($request);

            return redirect()->intended('/dashboard');
        }

        return view('accounts.select', [
            'accounts' => $this->activeAccountsFor($request->user()),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        if (Tenancy::isSingle()) {
            Tenancy::pinSingleAccountInSession($request);

            return redirect()->intended('/dashboard');
        }

        $data = $request->validate([
            'account_id' => ['required', 'integer'],
        ]);

        $account = $this->activeAccountsFor($request->user())
            ->firstWhere('id', (int) $data['account_id']);

        if (! $account) {
            return back()->withErrors([
                'account_id' => 'The selected account is not available for your user.',
            ]);
        }

        $request->session()->put('current_account_id', $account->id);

        return redirect()->intended('/dashboard');
    }

    private function activeAccountsFor(User $user)
    {
        if ($user->isSuperAdmin()) {
            return Account::query()
                ->where('status', Account::STATUS_ACTIVE)
                ->orderBy('account_name')
                ->get();
        }

        return $user->accounts()
            ->where('tbl_accounts.status', Account::STATUS_ACTIVE)
            ->wherePivot('status', AccountUser::STATUS_ACTIVE)
            ->orderBy('account_name')
            ->get();
    }
}
