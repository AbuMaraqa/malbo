<?php

namespace Modules\Store\Contracts;

/**
 * Where the storefront's Instagram posts come from: the home section and the campaign block read
 * this. The store ships the manual source (posts entered in the admin, ManualInstagramFeed); a Graph
 * API connection binds its own (or keeps the manual one and fills its table through
 * InstagramService::import).
 */
interface InstagramFeedSource
{
    /**
     * The newest posts to show, at most $limit, in their order.
     *
     * @return list<array{id: int|string, image: ?string, url: string, caption: ?string, products: list<array{name: string, url: string, image: ?string}>}>
     */
    public function posts(int $limit): array;
}
