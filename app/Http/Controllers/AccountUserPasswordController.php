<?php

namespace App\Http\Controllers;

use App\Models\AccountUser;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class AccountUserPasswordController extends Controller
{
    public function __construct()
    {
        $this->middleware(function (Request $request, Closure $next) {
            $this->ensureCanResetPasswords($request);

            return $next($request);
        });
    }

    public function edit(Request $request, int $accountUser): View
    {
        $membership = $this->membershipForCurrentAccount($request, $accountUser, ['user']);
        $this->guardAgainstSelfReset($request, $membership);

        return view('account-users.password', [
            'membership' => $membership,
        ]);
    }

    public function update(Request $request, int $accountUser): RedirectResponse
    {
        $membership = $this->membershipForCurrentAccount($request, $accountUser, ['user']);
        $this->guardAgainstSelfReset($request, $membership);

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

    protected function ensureCanResetPasswords(Request $request): void
    {
        $membership = AccountUser::query()
            ->where('account_id', $this->currentAccountId($request))
            ->where('user_id', $request->user()->id)
            ->where('status', AccountUser::STATUS_ACTIVE)
            ->first();

        if (! $membership || ! $membership->canManageAccountUsers()) {
            abort(403, 'You are not authorized to reset this password.');
        }
    }

    protected function membershipForCurrentAccount(Request $request, int $accountUserId, array $with = []): AccountUser
    {
        return AccountUser::query()
            ->where('account_id', $this->currentAccountId($request))
            ->with($with)
            ->findOrFail($accountUserId);
    }

    protected function guardAgainstSelfReset(Request $request, AccountUser $membership): void
    {
        if ((int) $membership->user_id === (int) $request->user()->id) {
            abort(403, 'You are not authorized to reset this password.');
        }
    }
}
