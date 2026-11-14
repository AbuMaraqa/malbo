<?php

namespace Modules\Reports\Support;

/**
 * Money as PostgreSQL returns it (a numeric string), shown without passing through a float: one
 * currency, the shekel (orders carry none of their own; the store prices in ₪).
 */
final class ReportMoney
{
    public const CURRENCY = '₪';

    /**
     * "1,234,567.50 ₪".
     */
    public static function format(string|int|float|null $value): string
    {
        return self::grouped(self::plain($value)).' '.self::CURRENCY;
    }

    /**
     * "1234567.50": two decimals, a dot, no separators (CSV).
     */
    public static function plain(string|int|float|null $value): string
    {
        $value = is_float($value) ? number_format($value, 2, '.', '') : trim((string) ($value ?? '0'));

        if (! preg_match('/^(-?)(\d*)(?:\.(\d*))?$/', $value, $parts)) {
            return '0.00';
        }

        $number = $parts[1].($parts[2] !== '' ? $parts[2] : '0').'.'.(($parts[3] ?? '') !== '' ? $parts[3] : '0');

        return self::round($number);
    }

    /**
     * Two decimals, half away from zero (as PostgreSQL's round()).
     */
    private static function round(string $number): string
    {
        $negative = str_starts_with($number, '-');
        $rounded = bcadd(ltrim($number, '-'), '0.005', 2);

        return $negative && bccomp($rounded, '0', 2) !== 0 ? '-'.$rounded : $rounded;
    }

    private static function grouped(string $plain): string
    {
        $negative = str_starts_with($plain, '-');
        [$whole, $cents] = explode('.', ltrim($plain, '-'));

        return ($negative ? '-' : '').strrev(implode(',', str_split(strrev($whole), 3))).'.'.$cents;
    }
}
