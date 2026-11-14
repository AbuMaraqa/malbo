<?php

namespace Modules\Items\Repositories\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Repositories\Contracts\RepositoryInterface;
use Modules\Items\Entities\Attribute;

interface AttributeRepositoryInterface extends RepositoryInterface
{
    /**
     * The query of the admin attributes table, with its eager loads (the table orders it).
     */
    public function tableQuery(): Builder;

    /**
     * An attribute with what its edit form shows: translations and its values in their order.
     */
    public function findForForm(int $id): Attribute;

    /**
     * Every attribute with its values and names, for the product form's variations generator.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Attribute>
     */
    public function withValues(): \Illuminate\Database\Eloquent\Collection;

    /**
     * The attributes the shop filters by, in id order, with their names and their active values in
     * their order.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Attribute>
     */
    public function filterable(): \Illuminate\Database\Eloquent\Collection;

    /**
     * The attribute staff marked as the size (`is_size`): product cards list its values. Null
     * while none is marked: cards then show no sizes.
     */
    public function sizeAttributeId(): ?int;
}
