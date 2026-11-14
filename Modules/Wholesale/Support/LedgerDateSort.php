<?php

namespace Modules\Wholesale\Support;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Sorts\Sort;

/**
 * Ledger entries by date, then in the order they were entered (the running balance's order).
 */
final class LedgerDateSort implements Sort
{
    public function __invoke(Builder $query, bool $descending, string $property): void
    {
        $direction = $descending ? 'desc' : 'asc';

        $query->orderBy('entry_date', $direction)->orderBy('id', $direction);
    }
}
