<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\User;
use App\Support\Demo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DemoLoginController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        abort_unless(Demo::isEnabled(), 404);

        $user = User::query()
            ->where('email', Demo::sharedUserEmail())
            ->where('status', User::STATUS_ACTIVE)
            ->firstOrFail();

        $account = Account::query()
            ->where('slug', Demo::accountSlug())
            ->where('status', Account::STATUS_ACTIVE)
            ->firstOrFail();

        Auth::login($user, false);
        $request->session()->regenerate();
        $request->session()->put('current_account_id', $account->id);

        return redirect()->route('dashboard');
    }
}
