<?php

namespace App\Http\Middleware;

use App\Models\AccountUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserBelongsToCurrentAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $accountId = $request->session()->get('current_account_id');

        if (! $accountId) {
            return redirect()->route('accounts.select');
        }

        // Account isolation: every account-scoped request must prove the
        // logged-in user still has an active membership for the session account.
        $belongsToAccount = AccountUser::where('user_id', $request->user()->id)
            ->where('account_id', $accountId)
            ->where('status', 'active')
            ->exists();

        if (! $belongsToAccount) {
            $request->session()->forget('current_account_id');

            return redirect()
                ->route('accounts.select')
                ->withErrors(['account_id' => 'Please select an account you can access.']);
        }

        return $next($request);
    }
}
