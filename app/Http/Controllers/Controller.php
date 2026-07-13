<?php

namespace App\Http\Controllers;

use App\Support\AppDateTime;
use Carbon\CarbonImmutable;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

abstract class Controller extends BaseController
{
    protected function currentAccountId(Request $request): int
    {
        return (int) $request->session()->get('current_account_id');
    }

    protected function normalizeDateInput(?string $value, string $field, bool $nullable = false): ?string
    {
        // Normalize user-facing DD-MM-YYYY values before they cross the
        // database boundary so storage can remain on native date columns.
        $normalizedDate = AppDateTime::normalizeDateInput($value);

        if ($normalizedDate === null && ! $nullable) {
            throw ValidationException::withMessages([
                $field => 'Use the DD-MM-YYYY date format.',
            ]);
        }

        return $normalizedDate;
    }

    protected function normalizeTimeInput(?string $value, string $field, bool $nullable = false): ?string
    {
        // Normalize user-facing HH:MM:SS values before they cross the
        // database boundary so storage can remain on native time values.
        $normalizedTime = AppDateTime::normalizeTimeInput($value);

        if ($normalizedTime === null && ! $nullable) {
            throw ValidationException::withMessages([
                $field => 'Use the HH:MM:SS time format.',
            ]);
        }

        return $normalizedTime;
    }

    protected function combineDateAndTimeInputs(?string $dateValue, ?string $timeValue, string $dateField, string $timeField): CarbonImmutable
    {
        // Combine the separate UI date and time inputs into a single datetime
        // value because the transaction table still stores a native datetime.
        $dateTime = AppDateTime::combineDateAndTime($dateValue, $timeValue);

        if (! $dateTime) {
            throw ValidationException::withMessages([
                $dateField => 'Use the DD-MM-YYYY date format.',
                $timeField => 'Use the HH:MM:SS time format.',
            ]);
        }

        return $dateTime;
    }

    protected function activeDictionaryValueRule(string $group, ?int $accountId = null): \Illuminate\Validation\Rules\Exists
    {
        // Validation stays aligned with the active dictionary rows so forms
        // cannot submit retired status values.
        return Rule::exists('tbl_data_dictionary', 'value')->where(function ($query) use ($group, $accountId) {
            $query->where('name', $group)
                ->where('is_active', true)
                ->where(function ($dictionaryQuery) use ($accountId) {
                    $dictionaryQuery->whereNull('account_id');

                    if ($accountId !== null) {
                        $dictionaryQuery->orWhere('account_id', $accountId);
                    }
                });
        });
    }
}
