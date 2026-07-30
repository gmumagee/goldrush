<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountBackup;
use App\Models\AuditLog;
use App\Models\Plan;
use App\Services\AccountPlanService;
use App\Support\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function __construct(protected AccountPlanService $accountPlanService)
    {
    }

    public function index(Request $request): View
    {
        $this->ensureAvailable();

        $accounts = Account::query()
            ->with('plan')
            ->withCount([
                'accountUsers as member_count' => fn ($query) => $query->where('status', 'active'),
                'machines as machine_count',
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
            'plans' => Plan::query()->ordered()->get(),
            'planUsageByAccount' => $accounts->getCollection()
                ->mapWithKeys(fn (Account $account) => [
                    $account->id => $this->accountPlanService->usageSummary($account, (int) $account->machine_count),
                ]),
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

    public function updatePlan(Request $request, Account $account): RedirectResponse
    {
        $this->ensureAvailable();

        $data = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
        ]);

        $newPlan = Plan::query()->findOrFail($data['plan_id']);

        if ((int) $account->plan_id !== (int) $newPlan->id) {
            $oldPlan = $account->plan;

            $account->loadCount('machines');
            $account->forceFill(['plan_id' => $newPlan->id])->save();

            AuditLog::query()->create([
                'account_id' => $account->id,
                'user_id' => $request->user()?->id,
                'auditable_type' => Account::class,
                'auditable_id' => $account->id,
                'event' => AuditLog::EVENT_UPDATED,
                'changes' => [
                    'plan' => [
                        'old' => $oldPlan?->name,
                        'new' => $newPlan->name,
                    ],
                    'machine_count_at_change' => $account->machineCount(),
                    'operator_action' => 'plan_changed',
                    'account_name' => $account->account_name,
                ],
                'created_at' => now(),
            ]);
        }

        return redirect()
            ->route('admin.accounts.index')
            ->with('status', sprintf('Plan updated to %s.', $newPlan->name));
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
