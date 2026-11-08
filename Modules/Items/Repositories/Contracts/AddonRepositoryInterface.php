<?php

namespace Modules\Items\Repositories\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Repositories\Contracts\RepositoryInterface;
use Modules\Items\Entities\Addon;

interface AddonRepositoryInterface extends RepositoryInterface
{
    /**
     * The query of the admin addons table, with its eager loads (the table orders it).
     */
    public function tableQuery(): Builder;

    /**
     * An addon with what its edit form shows: translations, and its options with theirs and their images.
     */
    public function findForForm(int $id): Addon;

    /**
     * Every addon with its options and names, for the product form.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Addon>
     */
    public function withOptions(): \Illuminate\Database\Eloquent\Collection;
}
