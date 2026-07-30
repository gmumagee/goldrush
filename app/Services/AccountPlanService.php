<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Plan;
use Illuminate\Validation\ValidationException;

class AccountPlanService
{
    public function loadLockedAccount(int $accountId): Account
    {
        return Account::query()
            ->with('plan')
            ->lockForUpdate()
            ->findOrFail($accountId);
    }

    public function usageSummary(Account $account, ?int $machineCount = null): array
    {
        $account->loadMissing('plan');

        $machineCount ??= $account->machineCount();
        $plan = $account->plan;
        $machineLimit = $plan?->machine_limit;
        $isUnlimited = $machineLimit === null;
        $remaining = $isUnlimited ? null : max(0, $machineLimit - $machineCount);
        $overage = $isUnlimited ? 0 : max(0, $machineCount - $machineLimit);
        $atLimit = ! $isUnlimited && $machineCount === $machineLimit;
        $overLimit = ! $isUnlimited && $machineCount > $machineLimit;
        $nearLimit = ! $isUnlimited && ! $atLimit && ! $overLimit && $remaining !== null && $remaining <= 2;
        $suggestedPlan = $this->suggestedPlanForMachineCount($account, $machineCount + 1);

        return [
            'machine_count' => $machineCount,
            'machine_limit' => $machineLimit,
            'limit_label' => $isUnlimited ? 'Unlimited' : (string) $machineLimit,
            'is_unlimited' => $isUnlimited,
            'remaining' => $remaining,
            'overage' => $overage,
            'at_limit' => $atLimit,
            'over_limit' => $overLimit,
            'near_limit' => $nearLimit,
            'plan' => $plan,
            'suggested_plan' => $suggestedPlan,
        ];
    }

    public function assertCanAddMachines(Account $account, int $additionalMachines = 1): void
    {
        if ($additionalMachines <= 0) {
            return;
        }

        $usage = $this->usageSummary($account);

        if ($usage['is_unlimited']) {
            return;
        }

        if (($usage['machine_count'] + $additionalMachines) <= $usage['machine_limit']) {
            return;
        }

        throw ValidationException::withMessages([
            'machine_limit' => $this->limitExceededMessage($account, $usage['machine_count']),
        ]);
    }

    public function limitExceededMessage(Account $account, ?int $machineCount = null): string
    {
        $account->loadMissing('plan');
        $usage = $this->usageSummary($account, $machineCount);

        if ($usage['is_unlimited']) {
            return '';
        }

        $baseMessage = sprintf(
            'Your %s plan allows up to %d machines.',
            $account->plan?->name ?? 'current',
            $usage['machine_limit'],
        );

        if ($usage['over_limit']) {
            return sprintf(
                '%s You are currently using %d. Upgrade to add more.',
                $baseMessage,
                $usage['machine_count'],
            );
        }

        return $baseMessage.' Upgrade to add more.';
    }

    public function suggestedPlanForMachineCount(Account $account, int $requiredMachineCount): ?Plan
    {
        $account->loadMissing('plan');
        $currentSortOrder = (int) ($account->plan?->sort_order ?? 0);

        return Plan::query()
            ->ordered()
            ->get()
            ->first(function (Plan $plan) use ($currentSortOrder, $requiredMachineCount): bool {
                return $plan->sort_order > $currentSortOrder
                    && $plan->supportsMachineCount($requiredMachineCount);
            });
    }

    public function minimumPlanForMachineCount(int $machineCount): ?Plan
    {
        return Plan::query()
            ->ordered()
            ->get()
            ->first(fn (Plan $plan) => $plan->supportsMachineCount($machineCount));
    }
}
