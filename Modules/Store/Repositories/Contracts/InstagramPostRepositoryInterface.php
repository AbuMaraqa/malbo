<?php

namespace Modules\Store\Repositories\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Core\Repositories\Contracts\RepositoryInterface;
use Modules\Store\Entities\InstagramPost;

interface InstagramPostRepositoryInterface extends RepositoryInterface
{
    /**
     * The admin table's query: posts with their picture and how many products they show.
     */
    public function tableQuery(): Builder;

    /**
     * A post with its picture and products, for its form.
     */
    public function forEdit(int $id): InstagramPost;

    /**
     * Active posts in their order (then the newest), at most $limit, with their picture and the
     * products they show that are published and active.
     *
     * @return Collection<int, InstagramPost>
     */
    public function forStorefront(int $limit): Collection;

    /**
     * The post a source already brought in under this id, if any.
     */
    public function findExternal(string $source, string $externalId): ?InstagramPost;
}
