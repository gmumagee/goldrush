<?php

namespace App\Http\Controllers;

use App\Models\AccountUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class AccountUserPasswordController extends Controller
{
    public function edit(Request $request, int $accountUser): View
    {
        $membership = $this->membershipForCurrentAccount($request, $accountUser, ['user']);
        $this->authorize('resetPassword', $membership);

        return view('account-users.password', [
            'membership' => $membership,
        ]);
    }

    public function update(Request $request, int $accountUser): RedirectResponse
    {
        $membership = $this->membershipForCurrentAccount($request, $accountUser, ['user']);
        $this->authorize('resetPassword', $membership);

        $validated = $request->validate([
            'password' => ['required', 'string', 'confirmed', PasswordRule::min(8)],
        ]);

        $membership->user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return redirect()
            ->route('account-users.index')
            ->with('status', 'User password reset successfully.');
    }
    protected function membershipForCurrentAccount(Request $request, int $accountUserId, array $with = []): AccountUser
    {
        return AccountUser::query()
            ->where('account_id', $this->currentAccountId($request))
            ->with($with)
            ->findOrFail($accountUserId);
    }

}
