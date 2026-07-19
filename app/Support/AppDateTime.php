<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Carbon\CarbonImmutable;
use DateTimeInterface;

class AppDateTime
{
    // Centralize the application display and input formats so every screen
    // uses the same date and time conventions.
    public const DISPLAY_DATE_FORMAT = 'd-m-Y';
    public const DISPLAY_TIME_FORMAT = 'H:i:s';
    public const STORAGE_DATE_FORMAT = 'Y-m-d';
    public const STORAGE_TIME_FORMAT = 'H:i:s';
    public const STORAGE_DATETIME_FORMAT = 'Y-m-d H:i:s';

    public static function displayDate(mixed $value, string $fallback = '—'): string
    {
        $date = self::toDisplayCarbon($value);

        return $date?->format(self::DISPLAY_DATE_FORMAT) ?? $fallback;
    }

    public static function displayTime(mixed $value, string $fallback = '—'): string
    {
        $time = self::toDisplayCarbon($value);

        return $time?->format(self::DISPLAY_TIME_FORMAT) ?? $fallback;
    }

    public static function isoDate(mixed $value): ?string
    {
        // Preserve a machine-readable date when the visible text uses the app display format.
        return self::toDisplayCarbon($value)?->format(self::STORAGE_DATE_FORMAT);
    }

    public static function isoDateTime(mixed $value): ?string
    {
        // Preserve a machine-readable datetime for <time> elements and audit-friendly markup.
        return self::toDisplayCarbon($value)?->toIso8601String();
    }

    public static function inputDate(mixed $value): string
    {
        return self::displayDate($value, '');
    }

    public static function inputTime(mixed $value): string
    {
        return self::displayTime($value, '');
    }

    public static function normalizeDateInput(?string $value): ?string
    {
        $value = self::clean($value);

        if ($value === null) {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('!'.self::DISPLAY_DATE_FORMAT, $value);

        if (! $date || $date->format(self::DISPLAY_DATE_FORMAT) !== $value) {
            return null;
        }

        return $date->format(self::STORAGE_DATE_FORMAT);
    }

    public static function normalizeTimeInput(?string $value): ?string
    {
        $value = self::clean($value);

        if ($value === null) {
            return null;
        }

        $time = CarbonImmutable::createFromFormat('!'.self::DISPLAY_TIME_FORMAT, $value);

        if (! $time || $time->format(self::DISPLAY_TIME_FORMAT) !== $value) {
            return null;
        }

        return $time->format(self::STORAGE_TIME_FORMAT);
    }

    public static function combineDateAndTime(?string $dateValue, ?string $timeValue): ?CarbonImmutable
    {
        $date = self::normalizeDateInput($dateValue);
        $time = self::normalizeTimeInput($timeValue);

        if ($date === null || $time === null) {
            return null;
        }

        return CarbonImmutable::createFromFormat(self::STORAGE_DATETIME_FORMAT, $date.' '.$time);
    }

    protected static function toCarbon(mixed $value): ?CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        if (is_string($value) && trim($value) !== '') {
            try {
                return CarbonImmutable::parse($value);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    protected static function toDisplayCarbon(mixed $value): ?CarbonInterface
    {
        // Normalize display values to the configured application timezone before formatting them.
        $date = self::toCarbon($value);

        return $date?->setTimezone((string) config('app.timezone', 'UTC'));
    }

    protected static function clean(?string $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        return $value === '' ? null : $value;
    }
}
