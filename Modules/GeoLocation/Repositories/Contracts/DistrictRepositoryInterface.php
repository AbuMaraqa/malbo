<?php

namespace Modules\GeoLocation\Repositories\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Repositories\Contracts\RepositoryInterface;

interface DistrictRepositoryInterface extends RepositoryInterface
{
    /**
     * The admin table: every district with its city, governorate and translations.
     */
    public function tableQuery(): Builder;
}
