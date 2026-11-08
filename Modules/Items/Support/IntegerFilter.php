<?php

namespace Modules\Items\Support;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * A table filter on an integer column (status, type, parent). PostgreSQL rejects a non-numeric or
 * out of range value with an error, so a value from the URL that is not an integer is ignored.
 */
final class IntegerFilter
{
    public static function make(string $name, ?string $column = null): AllowedFilter
    {
        return AllowedFilter::callback($name, function (Builder $query, mixed $value) use ($name, $column): void {
            $values = array_values(array_filter(
                array_map(
                    fn (mixed $item) => filter_var($item, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 2147483647]]),
                    (array) $value,
                ),
                fn (mixed $item) => $item !== false,
            ));

            if ($values !== []) {
                $query->whereIn($query->qualifyColumn($column ?? $name), $values);
            }
        });
    }
}
