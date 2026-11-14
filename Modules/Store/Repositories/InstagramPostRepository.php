<?php

namespace Modules\Store\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Core\Repositories\BaseEloquentRepository;
use Modules\Items\Enums\ProductActive;
use Modules\Items\Enums\ProductStatus;
use Modules\Store\Entities\InstagramPost;
use Modules\Store\Repositories\Contracts\InstagramPostRepositoryInterface;

class InstagramPostRepository extends BaseEloquentRepository implements InstagramPostRepositoryInterface
{
    public function __construct(InstagramPost $model)
    {
        parent::__construct($model);
    }

    public function tableQuery(): Builder
    {
        return $this->query()->with('media')->withCount('products');
    }

    public function forEdit(int $id): InstagramPost
    {
        /** @var InstagramPost */
        return $this->query()->with(['media', 'products.translations'])->findOrFail($id);
    }

    public function forStorefront(int $limit): Collection
    {
        return $this->query()
            ->where('is_active', true)
            ->with([
                'media',
                'products' => fn ($products) => $products
                    ->where($products->qualifyColumn('status'), ProductStatus::PUBLISHED)
                    ->where($products->qualifyColumn('is_active'), ProductActive::IS_ACTIVE->value),
                'products.translations',
                'products.media',
            ])
            ->orderBy('sort_order')
            ->orderByDesc('posted_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function findExternal(string $source, string $externalId): ?InstagramPost
    {
        /** @var InstagramPost|null */
        return $this->query()->where('source', $source)->where('external_id', $externalId)->first();
    }
}
