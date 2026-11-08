<?php

namespace Modules\Store\Repositories\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Core\Repositories\Contracts\RepositoryInterface;
use Modules\Store\Entities\Page;

interface PageRepositoryInterface extends RepositoryInterface
{
    /**
     * The admin pages table's query with its eager loads (the table orders it).
     */
    public function tableQuery(): Builder;

    public function findByKey(string $key): ?Page;

    public function findPublishedBySlug(string $slug): ?Page;

    /**
     * Published pages marked for the footer, in sort order.
     *
     * @return Collection<int, Page>
     */
    public function footerPages(): Collection;

    /**
     * Published pages, in sort order (the sitemap).
     *
     * @return Collection<int, Page>
     */
    public function published(): Collection;

    /**
     * The published system pages.
     *
     * @return array<string, string> key => slug
     */
    public function publishedKeys(): array;

    public function uniqueSlug(string $slug, ?int $ignoreId = null): string;
}
