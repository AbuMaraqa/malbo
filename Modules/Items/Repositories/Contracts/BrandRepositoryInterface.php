<?php

namespace Modules\Items\Repositories\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Repositories\Contracts\RepositoryInterface;

interface BrandRepositoryInterface extends RepositoryInterface
{
    /**
     * The query of the admin brands table, with its eager loads (the table orders it).
     */
    public function tableQuery(): Builder;

    /**
     * @return list<array{value: int, label: string}>
     */
    public function options(): array;
}
