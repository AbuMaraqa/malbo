<?php

namespace Modules\Core\Support;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * The server side of the table filters in resources/js/types/table.ts. A range filter named `key`
 * arrives as `filter[key_from]` and `filter[key_to]`, so it spreads into two allowed filters:
 * `->filters(AdminFilter::boolean('is_active'), ...AdminFilter::dateRange('created_at'))`.
 */
final class AdminFilter
{
    public static function exact(string $name, ?string $column = null): AllowedFilter
    {
        return AllowedFilter::exact($name, $column ?? $name);
    }

    /**
     * PostgreSQL has real booleans: the URL's 1 and 0 are converted before they reach the query.
     */
    public static function boolean(string $name, ?string $column = null): AllowedFilter
    {
        return AllowedFilter::callback($name, function (Builder $query, mixed $value) use ($name, $column): void {
            $query->where($column ?? $name, filter_var($value, FILTER_VALIDATE_BOOLEAN));
        });
    }

    /**
     * Case-insensitive text match on a column.
     */
    public static function text(string $name, ?string $column = null): AllowedFilter
    {
        return AllowedFilter::callback($name, function (Builder $query, mixed $value) use ($name, $column): void {
            $query->whereLike($column ?? $name, self::likeTerm($value));
        });
    }

    /**
     * Case-insensitive match on a translated column.
     */
    public static function translated(string $name, ?string $column = null): AllowedFilter
    {
        return AllowedFilter::callback($name, function (Builder $query, mixed $value) use ($name, $column): void {
            $query->whereHas('translations', fn (Builder $query) => $query->whereLike($column ?? $name, self::likeTerm($value)));
        });
    }

    /**
     * Rows linked to the chosen id through a relation.
     */
    public static function relation(string $name, string $relation, string $column = 'id'): AllowedFilter
    {
        return AllowedFilter::callback($name, function (Builder $query, mixed $value) use ($relation, $column): void {
            $query->whereHas($relation, fn (Builder $query) => $query->where($query->qualifyColumn($column), (int) $value));
        });
    }

    /**
     * @return array{0: AllowedFilter, 1: AllowedFilter}
     */
    public static function dateRange(string $name, ?string $column = null): array
    {
        $column ??= $name;

        return [
            AllowedFilter::callback("{$name}_from", function (Builder $query, mixed $value) use ($column): void {
                if (($date = self::date($value)) !== null) {
                    $query->whereDate($column, '>=', $date);
                }
            }),
            AllowedFilter::callback("{$name}_to", function (Builder $query, mixed $value) use ($column): void {
                if (($date = self::date($value)) !== null) {
                    $query->whereDate($column, '<=', $date);
                }
            }),
        ];
    }

    /**
     * @return array{0: AllowedFilter, 1: AllowedFilter}
     */
    public static function numberRange(string $name, ?string $column = null): array
    {
        $column ??= $name;

        return [
            AllowedFilter::callback("{$name}_from", function (Builder $query, mixed $value) use ($column): void {
                if (is_numeric($value)) {
                    $query->where($column, '>=', $value);
                }
            }),
            AllowedFilter::callback("{$name}_to", function (Builder $query, mixed $value) use ($column): void {
                if (is_numeric($value)) {
                    $query->where($column, '<=', $value);
                }
            }),
        ];
    }

    /**
     * A typed date, or null. PostgreSQL rejects a malformed date with an error, not an empty result.
     */
    private static function date(mixed $value): ?string
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $value);

        return $date && $date->format('Y-m-d') === (string) $value ? (string) $value : null;
    }

    private static function likeTerm(mixed $value): string
    {
        return '%'.addcslashes(trim((string) $value), '%_\\').'%';
    }
}
