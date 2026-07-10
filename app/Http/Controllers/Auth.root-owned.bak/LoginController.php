<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || $user->status !== 'active' || ! Hash::check($credentials['password'], $user->password)) {
            return back()
                ->withErrors(['email' => 'The provided credentials are invalid.'])
                ->onlyInput('email');
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $request->session()->forget('current_account_id');

        // Account isolation: only active account memberships are considered
        // when deciding where this authenticated user can continue.
        $accounts = $this->activeAccountsFor($user);

        if ($accounts->isEmpty()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()
                ->withErrors(['email' => 'Your user does not have access to an active account.'])
                ->onlyInput('email');
        }

        if ($accounts->count() === 1) {
            $request->session()->put('current_account_id', $accounts->first()->id);

            return redirect()->intended('/dashboard');
        }

        return redirect()->route('accounts.select');
    }

    private function activeAccountsFor(User $user)
    {
        return $user->accounts()
            ->where('tbl_accounts.status', 'active')
            ->wherePivot('status', 'active')
            ->orderBy('account_name')
            ->get();
    }
}
