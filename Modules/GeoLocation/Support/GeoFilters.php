<?php

namespace Modules\GeoLocation\Support;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\AllowedSort;

/**
 * Table and API filters for locations and branches. PostgreSQL answers a value of the wrong type
 * with an error, not an empty result, so every value is checked before it reaches the query.
 */
final class GeoFilters
{
    private const MAX_INTEGER = 2147483647;

    /**
     * An exact match on an integer column. Anything else in the URL is ignored.
     */
    public static function integer(string $name, ?string $column = null): AllowedFilter
    {
        return AllowedFilter::callback($name, function (Builder $query, mixed $value) use ($name, $column): void {
            $number = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => self::MAX_INTEGER]]);

            if ($number !== false) {
                $query->where($query->qualifyColumn($column ?? $name), $number);
            }
        });
    }

    /**
     * An exact match on a decimal column.
     */
    public static function decimal(string $name, ?string $column = null): AllowedFilter
    {
        return AllowedFilter::callback($name, function (Builder $query, mixed $value) use ($name, $column): void {
            if (is_numeric($value)) {
                $query->where($query->qualifyColumn($column ?? $name), $value);
            }
        });
    }

    /**
     * A range on an integer column, as `filter[name_from]` and `filter[name_to]`.
     *
     * @return array{0: AllowedFilter, 1: AllowedFilter}
     */
    public static function integerRange(string $name, ?string $column = null): array
    {
        $column ??= $name;

        return [
            AllowedFilter::callback("{$name}_from", function (Builder $query, mixed $value) use ($column): void {
                if (is_numeric($value)) {
                    $query->where($column, '>=', (int) min(ceil((float) $value), self::MAX_INTEGER));
                }
            }),
            AllowedFilter::callback("{$name}_to", function (Builder $query, mixed $value) use ($column): void {
                if (is_numeric($value)) {
                    $query->where($column, '<=', (int) min(floor((float) $value), self::MAX_INTEGER));
                }
            }),
        ];
    }

    /**
     * Rows that have a translation in the chosen language.
     */
    public static function locale(): AllowedFilter
    {
        return AllowedFilter::callback('locale', function (Builder $query, mixed $value): void {
            $query->whereHas('translations', fn (Builder $query) => $query->where('locale', (string) $value));
        });
    }

    /**
     * An API include whose rows are translated: serializing them reads their translations.
     */
    public static function includeWithTranslations(string $relation): AllowedInclude
    {
        return AllowedInclude::callback($relation, fn ($query) => $query->with('translations'));
    }

    /**
     * Sorting by the translated name in the current language.
     */
    public static function nameSort(): AllowedSort
    {
        return AllowedSort::callback('name', function (Builder $query, bool $descending): void {
            $relation = $query->getModel()->translations();

            $query->orderBy(
                $relation->getRelated()->newQuery()
                    ->select('name')
                    ->whereColumn($relation->getQualifiedForeignKeyName(), $query->getModel()->getQualifiedKeyName())
                    ->where('locale', app()->getLocale())
                    ->limit(1),
                $descending ? 'desc' : 'asc',
            );
        });
    }
}
