<?php

namespace Modules\GeoLocation\Repositories\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Repositories\Contracts\RepositoryInterface;

interface GovernorateRepositoryInterface extends RepositoryInterface
{
    /**
     * The admin table: every governorate with its country and translations.
     */
    public function tableQuery(): Builder;

    /**
     * Every governorate as a select option that knows its country, sorted by name.
     *
     * @return list<array{value: int, label: string, country_id: int}>
     */
    public function options(): array;

    /**
     * The codes in use, for a select filter.
     *
     * @return list<string>
     */
    public function codes(): array;
}
