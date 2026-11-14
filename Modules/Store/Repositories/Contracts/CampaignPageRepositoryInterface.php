<?php

namespace Modules\Store\Repositories\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Core\Repositories\Contracts\RepositoryInterface;
use Modules\Store\Entities\CampaignPage;

interface CampaignPageRepositoryInterface extends RepositoryInterface
{
    /**
     * The admin table's query: pages with their titles and how many blocks they hold.
     */
    public function tableQuery(): Builder;

    /**
     * A page with its translations and blocks (their pictures), for its form or the storefront.
     */
    public function withBlocks(int $id): CampaignPage;

    /**
     * The page under this slug, live or not (the caller decides who may see it).
     */
    public function findBySlug(string $slug): ?CampaignPage;

    /**
     * Slugs and last changes of the pages live now, for the sitemap.
     *
     * @return Collection<int, object{slug: string, updated_at: ?string}>
     */
    public function liveSlugs(): Collection;

    /**
     * $slug as a link part, with a number added while another page holds it.
     */
    public function uniqueSlug(string $slug, ?int $ignoreId = null): string;
}
