<?php

namespace Modules\Core\Repositories\Contracts;

use Illuminate\Database\Eloquent\Builder;

interface CurrencyRepositoryInterface extends RepositoryInterface
{
    /**
     * The admin table: every currency with its translations.
     */
    public function tableQuery(): Builder;
}
