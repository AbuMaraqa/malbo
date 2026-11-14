<?php

namespace Modules\Wholesale\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Modules\Core\Repositories\Contracts\RepositoryInterface;
use Modules\Wholesale\Entities\CatalogShare;

interface CatalogShareRepositoryInterface extends RepositoryInterface
{
    /**
     * The merchant's links that still work (not revoked, not expired), newest first.
     *
     * @return Collection<int, CatalogShare>
     */
    public function activeFor(int $merchantId): Collection;

    /**
     * One of the merchant's links, or null when it is someone else's.
     */
    public function findFor(int $merchantId, int $id): ?CatalogShare;

    /**
     * A link by its token, with its merchant and category.
     */
    public function findByToken(string $token): ?CatalogShare;
}
