<?php

namespace Modules\Reports\Support;

use Carbon\CarbonImmutable;

/**
 * How report numbers read on the page: bucket labels, changes, rates and averages. Money is
 * divided with bcmath, never as floats.
 */
final class ReportFormat
{
    /**
     * "4 Nov" for a day, "November 2026" for a month, in the page's language.
     */
    public static function bucket(string $key): string
    {
        $locale = app()->getLocale();

        return strlen($key) === 7
            ? CarbonImmutable::createFromFormat('!Y-m', $key)->locale($locale)->translatedFormat('F Y')
            : CarbonImmutable::createFromFormat('!Y-m-d', $key)->locale($locale)->translatedFormat('j M Y');
    }

    /**
     * The change from $before to $now in percent, one decimal; null when there was nothing before.
     */
    public static function change(string|int $now, string|int $before): ?float
    {
        if (bccomp((string) $before, '0', 2) === 0) {
            return null;
        }

        return round((float) bcdiv(bcmul(bcsub((string) $now, (string) $before, 2), '100', 2), (string) $before, 4), 1);
    }

    /**
     * $part of $whole in percent, one decimal (0 when the whole is 0).
     */
    public static function rate(string|int $part, string|int $whole): float
    {
        return bccomp((string) $whole, '0', 2) === 0 ? 0.0 : round((float) bcdiv(bcmul((string) $part, '100', 4), (string) $whole, 4), 1);
    }

    /**
     * Money per unit of $count (an average order value), as a numeric string.
     */
    public static function average(string $amount, int $count): string
    {
        return $count > 0 ? ReportMoney::plain(bcdiv($amount, (string) $count, 3)) : '0.00';
    }
}
