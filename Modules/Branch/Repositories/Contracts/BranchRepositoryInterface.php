<?php

namespace Modules\Branch\Repositories\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Branch\Entities\Branch;
use Modules\Core\Repositories\Contracts\RepositoryInterface;

interface BranchRepositoryInterface extends RepositoryInterface
{
    /**
     * The admin table: every branch with its translations, hours, country and city.
     */
    public function tableQuery(): Builder;

    /**
     * Open branches with their translations, for the storefront footer.
     *
     * @return Collection<int, Branch>
     */
    public function openForStorefront(): Collection;

    /**
     * Branches changed after a moment, for the sync endpoint.
     *
     * @return Collection<int, Branch>
     */
    public function changedSince(string $since): Collection;

    /**
     * Whether a branch, deleted ones included, points to the city.
     */
    public function existsInCity(int $cityId): bool;

    /**
     * Whether a branch, deleted ones included, points to the country.
     */
    public function existsInCountry(int $countryId): bool;
}
