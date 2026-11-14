<?php

namespace Modules\Store\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Core\Repositories\BaseEloquentRepository;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\OrderChannel;
use Modules\Items\Enums\OrderStatus;
use Modules\Store\Entities\Review;
use Modules\Store\Enums\ReviewStatus;
use Modules\Store\Repositories\Contracts\ReviewRepositoryInterface;

class ReviewRepository extends BaseEloquentRepository implements ReviewRepositoryInterface
{
    public function __construct(Review $model)
    {
        parent::__construct($model);
    }

    public function eligibleOrderId(int $userId, int $productId): ?int
    {
        $products = (new Product)->getTable();

        $id = Order::query()
            ->where('user_id', $userId)
            ->where('channel', OrderChannel::B2C)
            ->where('status', OrderStatus::DELIVERED)
            // The product or one of its variants, or the set or bundle the line was sold in.
            ->whereHas('items', fn (Builder $items) => $items->where(fn (Builder $sold) => $sold
                ->whereIn('product_id', fn ($ids) => $ids->select('id')->from($products)->where('id', $productId)->orWhere('parent_id', $productId))
                ->orWhere('bundle_product_id', $productId)))
            ->latest('id')
            ->value('id');

        return $id === null ? null : (int) $id;
    }

    public function lockForUser(int $userId, int $productId): ?Review
    {
        /** @var Review|null */
        return $this->query()->where('user_id', $userId)->where('product_id', $productId)->lockForUpdate()->first();
    }

    public function findForUser(int $userId, int $productId): ?Review
    {
        /** @var Review|null */
        return $this->query()->with('media')->where('user_id', $userId)->where('product_id', $productId)->first();
    }

    public function approvedForProduct(int $productId, int $perPage, string $pageName): LengthAwarePaginator
    {
        return $this->query()
            ->with(['user:id,name', 'media'])
            ->where('product_id', $productId)
            ->where('status', ReviewStatus::APPROVED)
            ->latest()
            ->orderByDesc('id')
            ->paginate($perPage, pageName: $pageName);
    }

    public function summary(int $productId): array
    {
        $row = $this->query()->toBase()
            ->where('product_id', $productId)
            ->where('status', ReviewStatus::APPROVED->value)
            ->selectRaw('count(*) as count, avg(rating) as average')
            ->first();

        $count = (int) ($row->count ?? 0);

        return ['average' => $count > 0 ? round((float) $row->average, 1) : null, 'count' => $count];
    }

    public function forUser(int $userId): Collection
    {
        return $this->query()
            ->with(['media', 'product' => fn ($product) => $product->withTrashed(), 'product.translations', 'product.media'])
            ->where('user_id', $userId)
            ->latest()
            ->orderByDesc('id')
            ->get();
    }

    public function tableQuery(): Builder
    {
        return $this->query()->with([
            'user:id,name,email',
            'media',
            'product' => fn ($product) => $product->withTrashed(),
            'product.translations',
        ]);
    }

    public function findForModeration(int $id): ?Review
    {
        /** @var Review|null */
        return $this->tableQuery()->with('order')->find($id);
    }

    public function reviewedProducts(): array
    {
        return Product::query()->withTrashed()->with('translations')
            ->whereIn('id', $this->query()->select('product_id'))
            ->orderBy('id')
            ->get()
            ->map(fn (Product $product) => ['value' => (int) $product->id, 'label' => (string) $product->name])
            ->all();
    }
}
