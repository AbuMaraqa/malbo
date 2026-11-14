<?php

namespace Modules\Wholesale\Repositories\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Core\Repositories\Contracts\RepositoryInterface;
use Modules\Wholesale\Entities\Tier;

interface TierRepositoryInterface extends RepositoryInterface
{
    /**
     * The admin table: tiers with their names and the number of merchants in each.
     */
    public function tableQuery(): Builder;

    public function forEdit(int $id): Tier;

    /**
     * The default tier, if one is set.
     */
    public function default(): ?Tier;

    /**
     * Every tier with its name, in sort order.
     *
     * @return Collection<int, Tier>
     */
    public function ordered(): Collection;

    /**
     * @return list<array{value: int, label: string}>
     */
    public function options(): array;
}
