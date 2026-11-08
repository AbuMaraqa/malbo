<?php

namespace Modules\Items\Repositories\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Repositories\Contracts\RepositoryInterface;

interface TagRepositoryInterface extends RepositoryInterface
{
    /**
     * The query of the admin tags table, with its eager loads (the table orders it).
     */
    public function tableQuery(): Builder;

    /**
     * Every name in the current locale, as suggestions for the product form.
     *
     * @return list<string>
     */
    public function names(): array;
}
