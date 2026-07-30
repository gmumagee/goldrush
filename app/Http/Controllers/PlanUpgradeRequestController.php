<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Plan;
use App\Models\PlanUpgradeRequest;
use App\Support\Demo;
use App\Support\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PlanUpgradeRequestController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        if (Demo::isEnabled()) {
            return back()->with('status', 'Upgrade requests are disabled in the public demo.');
        }

        $data = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'source' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        $plan = Plan::query()->findOrFail($data['plan_id']);
        $account = $this->currentAccountForIntent($request);

        PlanUpgradeRequest::create([
            'account_id' => $account?->id,
            'requested_by_user_id' => $request->user()?->id,
            'current_plan_id' => $account?->plan_id,
            'requested_plan_id' => $plan->id,
            'contact_email' => $account?->billing_email ?: $request->user()?->email,
            'source' => $data['source'],
            'machine_count' => $account?->machineCount(),
            // Placeholder until billing exists: this records the user's plan intent
            // so operators can follow up manually without charging in-app yet.
            'notes' => $data['notes'] ?? null,
        ]);

        $message = $account
            ? sprintf(
                '%s upgrade request recorded. Billing is not enabled yet; an admin will follow up manually.',
                $plan->name,
            )
            : sprintf(
                '%s plan interest recorded. Billing is not enabled yet; follow-up is still handled manually.',
                $plan->name,
            );

        return back()->with('status', $message);
    }

    protected function currentAccountForIntent(Request $request): ?Account
    {
        $accountId = Tenancy::currentAccountId($request);

        if (! $accountId) {
            return null;
        }

        return Account::query()
            ->with('plan')
            ->find($accountId);
    }
}
