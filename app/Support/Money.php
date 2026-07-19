<?php

namespace App\Support;

use InvalidArgumentException;

class Money
{
    public static function toCents(string|int|float|null $amount): int
    {
        if ($amount === null || $amount === '') {
            throw new InvalidArgumentException('A money amount is required.');
        }

        $normalized = str_replace([',', '$', ' '], '', trim((string) $amount));

        if (! preg_match('/^-?\d+(?:\.\d+)?$/', $normalized)) {
            throw new InvalidArgumentException('Invalid money amount: '.$amount);
        }

        $negative = str_starts_with($normalized, '-');
        $unsigned = ltrim($normalized, '+-');
        [$whole, $fraction] = array_pad(explode('.', $unsigned, 2), 2, '');
        $fractionDigits = preg_replace('/\D/', '', $fraction);
        $roundedFraction = substr(str_pad($fractionDigits, 3, '0'), 0, 3);
        $cents = ((int) $whole * 100) + (int) substr($roundedFraction, 0, 2);

        if ((int) $roundedFraction[2] >= 5) {
            $cents++;
        }

        return $negative ? -1 * $cents : $cents;
    }

    public static function fromCents(int $cents): string
    {
        $negative = $cents < 0 ? '-' : '';
        $absolute = abs($cents);

        return $negative.intdiv($absolute, 100).'.'.str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function format(string|int|float|null $amount): string
    {
        if ($amount === null || $amount === '') {
            return '—';
        }

        return '$'.number_format(self::toCents($amount) / 100, 2);
    }
}
