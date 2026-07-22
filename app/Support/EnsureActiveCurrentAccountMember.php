<?php

namespace App\Support;

use App\Models\AccountUser;
use App\Models\SuperAdminAuditLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveCurrentAccountMember
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $accountId = Tenancy::currentAccountId($request);

        if (! $accountId) {
            if (Tenancy::isSingle()) {
                abort(403, 'The configured single-tenant account is unavailable.');
            }

            return redirect()->route('accounts.select');
        }

        if ($user?->isSuperAdmin()) {
            $isMember = AccountUser::query()
                ->where('user_id', $user->id)
                ->where('account_id', $accountId)
                ->where('status', AccountUser::STATUS_ACTIVE)
                ->exists();

            if (! $isMember) {
                SuperAdminAuditLog::query()->create([
                    'user_id' => $user->id,
                    'account_id' => $accountId,
                    'action' => 'viewed_account',
                    'created_at' => now(),
                ]);
            }

            return $next($request);
        }

        // Account isolation: every account-scoped request must prove the
        // logged-in user still has an active membership for the session account.
        $belongsToAccount = AccountUser::query()
            ->where('user_id', $user->id)
            ->where('account_id', $accountId)
            ->where('status', AccountUser::STATUS_ACTIVE)
            ->exists();

        if (! $belongsToAccount) {
            $request->session()->forget('current_account_id');

            if (Tenancy::isSingle()) {
                abort(403, 'Your user does not have access to the configured account.');
            }

            return redirect()
                ->route('accounts.select')
                ->withErrors(['account_id' => 'Please select an account you can access.']);
        }

        return $next($request);
    }
}
