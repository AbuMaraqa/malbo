<?php

namespace Modules\Reports\Support;

use App\Support\Locales;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Modules\Core\Support\AdminFilter;
use Modules\Items\Enums\OrderChannel;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * What every report is narrowed by, read from `filter[...]` (the DataTable keeps these keys when it
 * pages or sorts): the period (`preset`, `date_from`, `date_to`), `channel` (b2c|b2b), `branch`,
 * `category`, `interval` (day|month) and `by` (a report's grouping). A value that is not valid is
 * ignored: report pages are GET pages and never answer with a validation error.
 */
final class ReportFilters
{
    /** The report-wide keys: applied inside the report's SQL, allowed as no-ops on its table. */
    public const KEYS = ['preset', 'date_from', 'date_to', 'channel', 'branch', 'category', 'interval', 'by'];

    public readonly string $interval;

    /**
     * @param  array<string, string>  $raw  the filter values as sent
     */
    private function __construct(
        public readonly ReportPeriod $period,
        public readonly ?OrderChannel $channel,
        public readonly ?int $branchId,
        public readonly ?int $categoryId,
        private readonly ?string $askedInterval,
        private readonly ?string $askedBy,
        private readonly array $raw,
    ) {
        $this->interval = $period->interval($askedInterval);
    }

    public static function fromRequest(Request $request): self
    {
        $filter = $request->input('filter', []);
        $filter = is_array($filter) ? array_map(fn ($value) => is_scalar($value) ? trim((string) $value) : '', $filter) : [];

        return self::fromArray($filter);
    }

    /**
     * @param  array<string, string>  $filter
     */
    public static function fromArray(array $filter): self
    {
        $id = fn (?string $value): ?int => $value !== null && ctype_digit($value) && (int) $value > 0 ? (int) $value : null;

        return new self(
            ReportPeriod::resolve($filter['preset'] ?? null, $filter['date_from'] ?? null, $filter['date_to'] ?? null),
            OrderChannel::tryFrom((string) ($filter['channel'] ?? '')),
            $id($filter['branch'] ?? null),
            $id($filter['category'] ?? null),
            in_array($filter['interval'] ?? null, ['day', 'month'], true) ? $filter['interval'] : null,
            isset($filter['by']) && preg_match('/^[a-z_]{1,20}$/', $filter['by']) ? $filter['by'] : null,
            $filter,
        );
    }

    /**
     * The same filters over another period (the comparison with the period before).
     */
    public function withPeriod(ReportPeriod $period): self
    {
        return new self($period, $this->channel, $this->branchId, $this->categoryId, $this->askedInterval, $this->askedBy, $this->raw);
    }

    public function withChannel(?OrderChannel $channel): self
    {
        return new self($this->period, $channel, $this->branchId, $this->categoryId, $this->askedInterval, $this->askedBy, $this->raw);
    }

    /**
     * The report's grouping: the asked one when the report offers it, else its default.
     *
     * @param  list<string>  $allowed
     */
    public function by(array $allowed, string $default): string
    {
        return in_array($this->askedBy, $allowed, true) ? $this->askedBy : $default;
    }

    /**
     * The language names and option values are shown in (an app locale, so it may be written into SQL).
     */
    public function locale(): string
    {
        $locale = app()->getLocale();

        return in_array($locale, Locales::keys(), true) ? $locale : (string) config('app.fallback_locale', 'ar');
    }

    /**
     * The filter values a link to the same report (its CSV export) carries: the resolved period.
     *
     * @return array<string, string>
     */
    public function query(): array
    {
        return array_filter([
            'date_from' => $this->period->from->format('Y-m-d'),
            'date_to' => $this->period->to->format('Y-m-d'),
            'channel' => $this->channel?->value,
            'branch' => $this->branchId !== null ? (string) $this->branchId : null,
            'category' => $this->categoryId !== null ? (string) $this->categoryId : null,
            'interval' => $this->askedInterval,
            'by' => $this->askedBy,
            // The table's own refinements travel with the export too.
            ...array_diff_key($this->raw, array_flip(self::KEYS)),
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * What the filter bar shows.
     *
     * @return array{preset: string, date_from: string, date_to: string, channel: ?string, branch: ?int, category: ?int, interval: string, by: ?string, timezone: string}
     */
    public function toProps(): array
    {
        return [
            'preset' => $this->period->preset,
            'date_from' => $this->period->from->format('Y-m-d'),
            'date_to' => $this->period->to->format('Y-m-d'),
            'channel' => $this->channel?->value,
            'branch' => $this->branchId,
            'category' => $this->categoryId,
            'interval' => $this->interval,
            'by' => $this->askedBy,
            'timezone' => ReportPeriod::timezone(),
        ];
    }

    /**
     * The report-wide keys, allowed on a report's table without touching its outer query: the
     * report's SQL already applied them.
     *
     * @return list<AllowedFilter>
     */
    public static function allowed(): array
    {
        return array_map(fn (string $key) => AllowedFilter::callback($key, fn (Builder $query) => $query), self::KEYS);
    }

    /**
     * A table's free-text search over some of its (aggregated) columns, case-insensitive.
     *
     * @param  list<string>  $columns
     */
    public static function search(array $columns): AllowedFilter
    {
        return AllowedFilter::callback('search', function (Builder $query, mixed $value) use ($columns): void {
            // "Shirt, red" arrives split on its comma: searched for whole.
            $term = AdminFilter::likeTerm($value);

            $query->where(function (Builder $query) use ($columns, $term): void {
                foreach ($columns as $column) {
                    $query->orWhereLike($column, $term, caseSensitive: false);
                }
            });
        });
    }

    /**
     * The table's rows for a CSV: every row (no page), the table's own filters (search, ranges)
     * applied as on the page.
     *
     * @param  Builder  $query  a report table's query (`ReportRow::query()->fromSub(...)`)
     * @param  list<AllowedFilter>  $filters  the table's filters
     */
    public function exportQuery(Builder $query, array $filters): \Illuminate\Database\Query\Builder
    {
        $request = Request::create('/', 'GET', ['filter' => $this->raw]);

        return QueryBuilder::for($query, $request)->allowedFilters(...$filters)->getEloquentBuilder()->toBase();
    }
}
