<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Location;
use App\Models\Service;
use App\Support\Money;

class CommissionCalculationService
{
    public function calculateForAccount(int $accountId, string $dateFrom, string $dateTo): array
    {
        $locations = Location::query()
            ->where('account_id', $accountId)
            ->whereNotNull('commission_rate')
            ->notInventory()
            ->orderBy('location_name')
            ->get(['id', 'location_name', 'commission_rate', 'commission_on_net']);

        if ($locations->isEmpty()) {
            return [
                'total_cents' => 0,
                'locations' => collect(),
            ];
        }

        $salesByLocation = Service::query()
            ->selectRaw('location_id, COALESCE(SUM(amount_collected), 0) as total_amount_collected')
            ->where('account_id', $accountId)
            ->whereNotNull('amount_collected')
            ->whereDate('service_date', '>=', $dateFrom)
            ->whereDate('service_date', '<=', $dateTo)
            ->whereRaw('LOWER(TRIM(service_type)) = ?', [Service::TYPE_LOCATION])
            ->whereIn('location_id', $locations->pluck('id')->all())
            ->groupBy('location_id')
            ->pluck('total_amount_collected', 'location_id');

        $expensesByLocation = Expense::query()
            ->selectRaw('location_id, COALESCE(SUM(amount), 0) as total_amount')
            ->where('account_id', $accountId)
            ->whereNotNull('location_id')
            ->whereDate('expense_date', '>=', $dateFrom)
            ->whereDate('expense_date', '<=', $dateTo)
            ->whereIn('location_id', $locations->pluck('id')->all())
            ->groupBy('location_id')
            ->pluck('total_amount', 'location_id');

        $breakdown = $locations->map(function (Location $location) use ($salesByLocation, $expensesByLocation) {
            $salesCents = $this->toCentsOrZero($salesByLocation[$location->id] ?? '0');
            $locationExpenseCents = $this->toCentsOrZero($expensesByLocation[$location->id] ?? '0');
            $basisBeforeFloorCents = $location->commission_on_net
                ? $salesCents - $locationExpenseCents
                : $salesCents;
            $basisCents = max(0, $basisBeforeFloorCents);
            $commissionRate = (string) $location->commission_rate;
            $commissionCents = $this->multiplyMoneyByRate($basisCents, $commissionRate);

            return [
                'location_id' => $location->id,
                'location_name' => $location->location_name,
                'basis_type' => $location->commission_on_net ? 'net' : 'gross',
                'sales_cents' => $salesCents,
                'sales_display' => $this->formatCurrencyFromCents($salesCents),
                'location_expenses_cents' => $locationExpenseCents,
                'location_expenses_display' => $this->formatCurrencyFromCents($locationExpenseCents),
                'basis_cents' => $basisCents,
                'basis_display' => $this->formatCurrencyFromCents($basisCents),
                'basis_was_floored' => $basisBeforeFloorCents < 0,
                'commission_rate' => $commissionRate,
                'commission_rate_percent' => number_format((float) $commissionRate * 100, 2, '.', ''),
                'commission_cents' => $commissionCents,
                'commission_display' => $this->formatCurrencyFromCents($commissionCents),
            ];
        })->values();

        return [
            'total_cents' => $breakdown->sum('commission_cents'),
            'locations' => $breakdown,
        ];
    }

    protected function multiplyMoneyByRate(int $cents, string $rate): int
    {
        $normalizedRate = trim($rate);

        if ($normalizedRate === '') {
            return 0;
        }

        if (! str_contains($normalizedRate, '.')) {
            return $cents * (int) $normalizedRate;
        }

        [$whole, $fraction] = array_pad(explode('.', $normalizedRate, 2), 2, '');
        $fraction = rtrim($fraction, '0');
        $scale = strlen($fraction);
        $rateInteger = (int) ($whole.$fraction);
        $divisor = 10 ** $scale;

        return (int) round(($cents * $rateInteger) / $divisor);
    }

    protected function toCentsOrZero(string|int|float|null $amount): int
    {
        if ($amount === null || $amount === '') {
            return 0;
        }

        return Money::toCents($amount);
    }

    protected function formatCurrencyFromCents(int $cents): string
    {
        $prefix = $cents < 0 ? '-' : '';

        return $prefix.'$'.number_format(abs($cents) / 100, 2);
    }
}
