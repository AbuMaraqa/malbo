<?php

namespace Tests\Unit\Reports;

use Carbon\CarbonImmutable;
use Modules\Reports\Support\ReportPeriod;
use Tests\TestCase;

/**
 * The days a report covers: presets, custom ranges, the reports' timezone.
 */
class ReportPeriodTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['reports.timezone' => 'Asia/Hebron', 'app.timezone' => 'UTC']);
    }

    private function today(string $date = '2026-11-13'): CarbonImmutable
    {
        return CarbonImmutable::parse($date, 'Asia/Hebron');
    }

    private function range(ReportPeriod $period): string
    {
        return $period->from->format('Y-m-d').'..'.$period->to->format('Y-m-d').' '.$period->preset;
    }

    public function test_presets(): void
    {
        $this->assertSame('2026-11-13..2026-11-13 today', $this->range(ReportPeriod::resolve('today', null, null, $this->today())));
        $this->assertSame('2026-11-07..2026-11-13 7d', $this->range(ReportPeriod::resolve('7d', null, null, $this->today())));
        $this->assertSame('2026-11-01..2026-11-13 this_month', $this->range(ReportPeriod::resolve('this_month', null, null, $this->today())));
        $this->assertSame('2026-10-01..2026-10-31 last_month', $this->range(ReportPeriod::resolve('last_month', null, null, $this->today())));
        $this->assertSame('2025-12-01..2025-12-31 last_month', $this->range(ReportPeriod::resolve('last_month', null, null, $this->today('2026-01-15'))));
        $this->assertSame('2026-11-01..2026-11-13 this_month', $this->range(ReportPeriod::resolve(null, null, null, $this->today())));
    }

    public function test_explicit_dates_win_and_match_a_preset(): void
    {
        $this->assertSame('2026-10-01..2026-10-31 last_month', $this->range(ReportPeriod::resolve('today', '2026-10-01', '2026-10-31', $this->today())));
        $this->assertSame('2026-09-05..2026-09-20 custom', $this->range(ReportPeriod::resolve('custom', '2026-09-05', '2026-09-20', $this->today())));
        $this->assertSame('2026-09-05..2026-09-05 custom', $this->range(ReportPeriod::resolve(null, null, '2026-09-05', $this->today())));
    }

    public function test_invalid_or_reversed_dates_fall_back_to_this_month(): void
    {
        foreach ([['2026-02-30', '2026-03-01'], ['2026-10-10', '2026-10-01'], ['nonsense', null], ['2010-01-01', '2026-01-01']] as [$from, $to]) {
            $this->assertSame('2026-11-01..2026-11-13 this_month', $this->range(ReportPeriod::resolve(null, $from, $to, $this->today())), "{$from}..{$to}");
        }
        $this->assertSame('2026-11-01..2026-11-13 this_month', $this->range(ReportPeriod::resolve('forever', null, null, $this->today())));
    }

    public function test_bounds_are_local_midnights_in_the_app_timezone(): void
    {
        $period = ReportPeriod::resolve('today', null, null, $this->today());

        $this->assertSame(['2026-11-12 22:00:00', '2026-11-13 22:00:00'], $period->bounds());

        // Summer time: +3.
        $this->assertSame(['2026-07-09 21:00:00', '2026-07-10 21:00:00'], ReportPeriod::resolve(null, '2026-07-10', '2026-07-10', $this->today())->bounds());
    }

    public function test_previous_period(): void
    {
        $previous = ReportPeriod::resolve(null, '2026-11-01', '2026-11-10', $this->today())->previous();

        $this->assertSame('2026-10-22..2026-10-31', $previous->from->format('Y-m-d').'..'.$previous->to->format('Y-m-d'));
        $this->assertSame(10, $previous->days());
    }

    public function test_month_presets_compare_with_the_previous_month(): void
    {
        $span = fn (ReportPeriod $period) => $period->from->format('Y-m-d').'..'.$period->to->format('Y-m-d');

        // This month so far: the same days of the month before.
        $this->assertSame('2026-10-01..2026-10-13', $span(ReportPeriod::resolve('this_month', null, null, $this->today())->previous()));
        // A shorter month before: up to its last day.
        $this->assertSame('2026-02-01..2026-02-28', $span(ReportPeriod::resolve('this_month', null, null, $this->today('2026-03-31'))->previous()));
        // Last month: the whole month before it.
        $this->assertSame('2026-09-01..2026-09-30', $span(ReportPeriod::resolve('last_month', null, null, $this->today())->previous()));
        $this->assertSame('2025-11-01..2025-11-30', $span(ReportPeriod::resolve('last_month', null, null, $this->today('2026-01-15'))->previous()));
        // Days: the same number just before.
        $this->assertSame('2026-10-31..2026-11-06', $span(ReportPeriod::resolve('7d', null, null, $this->today())->previous()));
        $this->assertSame('2026-11-12..2026-11-12', $span(ReportPeriod::resolve('today', null, null, $this->today())->previous()));
    }

    public function test_buckets_fill_the_range(): void
    {
        $period = ReportPeriod::resolve(null, '2026-10-30', '2026-11-02', $this->today());

        $this->assertSame(['2026-10-30', '2026-10-31', '2026-11-01', '2026-11-02'], $period->buckets('day'));
        $this->assertSame(['2026-10', '2026-11'], $period->buckets('month'));
        $this->assertSame('day', $period->interval(null));
        $this->assertSame('month', ReportPeriod::resolve(null, '2026-01-01', '2026-11-13', $this->today())->interval(null));
        $this->assertSame('day', ReportPeriod::resolve(null, '2026-01-01', '2026-11-13', $this->today())->interval('day'));
        $this->assertSame('month', ReportPeriod::resolve(null, '2024-01-01', '2026-11-13', $this->today())->interval('day'));
    }
}
