<?php

namespace Modules\GeoLocation\Repositories\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Repositories\Contracts\RepositoryInterface;

interface CountryRepositoryInterface extends RepositoryInterface
{
    /**
     * The admin table: every country with its currency and translations.
     */
    public function tableQuery(): Builder;

    /**
     * Every country as a select option, sorted by name.
     *
     * @return list<array{value: int, label: string}>
     */
    public function options(): array;

    /**
     * The values a column holds, for a select filter.
     *
     * @return list<string>
     */
    public function distinctValues(string $column): array;
}
