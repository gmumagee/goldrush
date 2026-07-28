<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountBackup;
use App\Models\AuditLog;
use App\Support\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function index(Request $request): View
    {
        $this->ensureAvailable();

        $accounts = Account::query()
            ->withCount([
                'accountUsers as member_count' => fn ($query) => $query->where('status', 'active'),
            ])
            ->orderBy('account_name')
            ->orderBy('id')
            ->paginate(25);

        $backupsByAccount = AccountBackup::query()
            ->with('requestedBy')
            ->whereIn('account_id', $accounts->getCollection()->pluck('id'))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy('account_id');

        return view('admin.accounts.index', [
            'accounts' => $accounts,
            'backupsByAccount' => $backupsByAccount,
        ]);
    }

    public function block(Request $request, Account $account): RedirectResponse
    {
        $this->ensureAvailable();

        if ($response = $this->guardSelfLockout($request, $account)) {
            return $response;
        }

        return $this->setStatus($request, $account, Account::STATUS_INACTIVE, 'blocked');
    }

    public function unblock(Request $request, Account $account): RedirectResponse
    {
        $this->ensureAvailable();

        return $this->setStatus($request, $account, Account::STATUS_ACTIVE, 'unblocked');
    }

    protected function ensureAvailable(): void
    {
        abort_if(Tenancy::isSingle(), 404);
    }

    protected function guardSelfLockout(Request $request, Account $account): ?RedirectResponse
    {
        if ((int) $request->session()->get('current_account_id') !== (int) $account->id) {
            return null;
        }

        return redirect()
            ->route('admin.accounts.index')
            ->withErrors(['account' => 'You cannot block the account currently selected in your session.']);
    }

    protected function setStatus(Request $request, Account $account, string $status, string $action): RedirectResponse
    {
        if ($account->status !== $status) {
            $oldStatus = $account->status;
            $account->forceFill(['status' => $status])->save();

            AuditLog::query()->create([
                'account_id' => $account->id,
                'user_id' => $request->user()?->id,
                'auditable_type' => Account::class,
                'auditable_id' => $account->id,
                'event' => AuditLog::EVENT_UPDATED,
                'changes' => [
                    'status' => [
                        'old' => $oldStatus,
                        'new' => $status,
                    ],
                    'operator_action' => $action,
                    'account_name' => $account->account_name,
                ],
                'created_at' => now(),
            ]);
        }

        return redirect()
            ->route('admin.accounts.index')
            ->with('status', sprintf('Account %s successfully.', $action));
    }
}
