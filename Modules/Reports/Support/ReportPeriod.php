<?php

namespace Modules\Reports\Support;

use Carbon\CarbonImmutable;
use DateTimeZone;

/**
 * The days a report covers, in the reports' timezone (`config('reports.timezone')`, else the app's):
 * a preset (today, the last 7 days, this month, last month) or a custom range. Explicit dates win
 * over a preset; anything invalid falls back to this month. Timestamps are stored in the app
 * timezone, so `bounds()` turns the local days into app-timezone instants for the WHERE.
 */
final class ReportPeriod
{
    public const PRESETS = ['today', '7d', 'this_month', 'last_month'];

    public const DEFAULT = 'this_month';

    /** The longest range a report accepts, in days (about five years). */
    public const MAX_DAYS = 1830;

    private function __construct(
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly string $preset,
    ) {}

    /**
     * @param  CarbonImmutable|null  $today  the reports' today (tests pass one)
     */
    public static function resolve(?string $preset, ?string $from, ?string $to, ?CarbonImmutable $today = null): self
    {
        $today = ($today ?? CarbonImmutable::now(self::timezone()))->startOfDay();
        $start = self::date($from);
        $end = self::date($to);

        // A date that was sent but is not a date spoils the range: no half-guessed periods.
        $malformed = (filled($from) && $start === null) || (filled($to) && $end === null);

        if (! $malformed && ($start !== null || $end !== null)) {
            $start ??= $end;
            $end ??= $today->max($start);

            if ($end->gte($start) && $start->diffInDays($end) < self::MAX_DAYS) {
                return new self($start, $end, self::match($start, $end, $today));
            }
        }

        $preset = in_array($preset, self::PRESETS, true) ? $preset : self::DEFAULT;
        [$start, $end] = self::range($preset, $today);

        return new self($start, $end, $preset);
    }

    /**
     * The reports' timezone: `REPORTS_TIMEZONE`, else the app's.
     */
    public static function timezone(): string
    {
        $zone = (string) (config('reports.timezone') ?: config('app.timezone'));

        return in_array($zone, DateTimeZone::listIdentifiers(), true) || $zone === 'UTC' ? $zone : 'UTC';
    }

    /**
     * The timezone timestamps are stored in.
     */
    public static function storedTimezone(): string
    {
        $zone = (string) config('app.timezone');

        return in_array($zone, DateTimeZone::listIdentifiers(), true) || $zone === 'UTC' ? $zone : 'UTC';
    }

    /**
     * Start (inclusive) and end (exclusive), as stored timestamps: local midnights in the app timezone.
     *
     * @return array{0: string, 1: string}
     */
    public function bounds(): array
    {
        $zone = self::storedTimezone();

        return [
            $this->from->setTimezone($zone)->format('Y-m-d H:i:s'),
            $this->to->addDay()->setTimezone($zone)->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * What this period is compared with: for "this month" the same days of the month before (up to
     * its last day), for "last month" the whole month before it, else the same number of days just
     * before.
     */
    public function previous(): self
    {
        if ($this->preset === 'this_month' || $this->preset === 'last_month') {
            $from = $this->from->subMonthNoOverflow()->startOfMonth();
            $to = $this->preset === 'last_month'
                ? $from->endOfMonth()->startOfDay()
                : $from->addDays($this->days() - 1)->min($from->endOfMonth()->startOfDay());

            return new self($from, $to, 'custom');
        }

        $days = $this->days();

        return new self($this->from->subDays($days), $this->from->subDay(), 'custom');
    }

    public function days(): int
    {
        return (int) $this->from->diffInDays($this->to) + 1;
    }

    /**
     * Every bucket key the period covers: `Y-m-d` days or `Y-m` months.
     *
     * @return list<string>
     */
    public function buckets(string $interval): array
    {
        $keys = [];

        if ($interval === 'month') {
            for ($month = $this->from->startOfMonth(); $month->lte($this->to); $month = $month->addMonthNoOverflow()) {
                $keys[] = $month->format('Y-m');
            }

            return $keys;
        }

        for ($day = $this->from; $day->lte($this->to); $day = $day->addDay()) {
            $keys[] = $day->format('Y-m-d');
        }

        return $keys;
    }

    /**
     * Days up to two months, months beyond (a day chart over a year is unreadable).
     */
    public function interval(?string $asked): string
    {
        if ($asked === 'month' || ($asked === 'day' && $this->days() <= 366)) {
            return $asked;
        }

        return $this->days() <= 62 ? 'day' : 'month';
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private static function range(string $preset, CarbonImmutable $today): array
    {
        return match ($preset) {
            'today' => [$today, $today],
            '7d' => [$today->subDays(6), $today],
            'last_month' => [$today->startOfMonth()->subMonthNoOverflow(), $today->startOfMonth()->subDay()],
            default => [$today->startOfMonth(), $today],
        };
    }

    private static function match(CarbonImmutable $from, CarbonImmutable $to, CarbonImmutable $today): string
    {
        foreach (self::PRESETS as $preset) {
            [$start, $end] = self::range($preset, $today);

            if ($start->isSameDay($from) && $end->isSameDay($to)) {
                return $preset;
            }
        }

        return 'custom';
    }

    private static function date(?string $value): ?CarbonImmutable
    {
        if ($value === null || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, self::timezone());

        return $date !== false && $date->format('Y-m-d') === $value ? $date : null;
    }
}
