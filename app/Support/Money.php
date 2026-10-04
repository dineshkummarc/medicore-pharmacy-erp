<?php

namespace App\Support;

/**
 * Integer-cent arithmetic for monetary values.
 *
 * Amounts are stored as DECIMAL(…, 2) but every calculation in the services is
 * performed in integer cents to avoid floating-point drift.
 */
final class Money
{
    public static function toCents(string|int|float|null $amount): int
    {
        if ($amount === null || $amount === '') {
            return 0;
        }

        return (int) round(((float) $amount) * 100);
    }

    public static function fromCents(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    /**
     * Percentage of an amount in cents, rounded half-up to the cent.
     */
    public static function percentOf(int $cents, string|int|float $percent): int
    {
        return (int) round($cents * ((float) $percent) / 100);
    }

    public static function format(string|int|float|null $amount, ?string $symbol = null): string
    {
        $symbol ??= settings('currency_symbol', '$');
        $value = (float) ($amount ?? 0);
        $sign = $value < 0 ? '-' : '';

        return $sign.$symbol.number_format(abs($value), 2);
    }
}
