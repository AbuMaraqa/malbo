<?php

namespace Modules\Items\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\Items\Repositories\Contracts\AddonRepositoryInterface;
use Modules\Items\Repositories\Contracts\OfferRepositoryInterface;
use Modules\Items\Entities\AddonOption;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\Product;
use Modules\Items\Transformers\V1\AddonOptionResource;
use Modules\Items\Transformers\V1\AddonResource;
use Modules\Items\Transformers\V1\CategoryResource;
use Modules\Items\Transformers\V1\OfferResource;
use Modules\Items\Transformers\V1\OrderResource;
use Modules\Items\Transformers\V1\ProductResource;
use Modules\Items\Repositories\Contracts\CategoryRepositoryInterface;
use Modules\Items\Repositories\Contracts\ProductRepositoryInterface;

class SyncController extends Controller
{
    public function syncProducts(Request $request, ProductRepositoryInterface $products)
    {
        $since = $this->since($request);

        $products = $products->changedSince($since);

        $this->hydrateAddonCustomOptions($products);

        return ProductResource::collection($products)
            ->additional([
                'meta' => [
                    'timestamp' => now()->toDateTimeString(),
                ]
            ]);
    }

    public function syncCategories(Request $request)
    {
        $since = $this->since($request);

        $categories = app(CategoryRepositoryInterface::class)->changedSince($since);

        return CategoryResource::collection($categories)
            ->additional([
                'meta' => [
                    'timestamp' => now()->toDateTimeString(),
                ]
            ]);
    }

    public function syncAddons(Request $request){
        $since = $this->since($request);

        $addons = app(AddonRepositoryInterface::class)->query()
            ->with([
                'media',
                'translations',
                'addonOptions.media',
                'addonOptions.translations',
            ])
            ->where('updated_at', '>', $since)
            ->orderBy('updated_at')
            ->orderBy('id')
            ->get();

        return AddonResource::collection($addons)
            ->additional([
                'meta' => [
                    'timestamp' => now()->toDateTimeString(),
                ]
            ]);
    }

    public function syncAddonsOptions(Request $request){
        $since = $this->since($request);

        $addonsOptions = AddonOption::query()
            ->with(['media', 'translations'])
            ->where('updated_at', '>', $since)
            ->orderBy('updated_at')
            ->orderBy('id')
            ->get();

        return AddonOptionResource::collection($addonsOptions)
            ->additional([
                'meta' => [
                    'timestamp' => now()->toDateTimeString(),
                ]
            ]);
    }

    public function syncOffers(Request $request){
        $since = $this->since($request);

        $offers = app(OfferRepositoryInterface::class)->query()
            ->with(['media', 'translations'])
            ->where('updated_at', '>', $since)
            ->orderBy('updated_at')
            ->orderBy('id')
            ->get();

        return OfferResource::collection($offers)
            ->additional([
                'meta' => [
                    'timestamp' => now()->toDateTimeString(),
                ]
            ]);
    }

    public function syncOrders(Request $request){
        $since = $this->since($request);

        $orders = Order::query()
            ->with([
                'user',
                'items.product.media',
                'items.product.translations',
                'items.addonOptions.media',
                'items.addonOptions.translations',
            ])
            ->where('updated_at', '>', $since)
            ->orderBy('updated_at')
            ->orderBy('id')
            ->get();

        return OrderResource::collection($orders)
            ->additional([
                'meta' => [
                    'timestamp' => now()->toDateTimeString(),
                ]
            ]);
    }

    /**
     * PostgreSQL rejects a malformed date with an error, not an empty result.
     */
    private function since(Request $request): string
    {
        return $request->validate(['since' => ['nullable', 'date']])['since'] ?? '1970-01-01 00:00:00';
    }

    private function hydrateAddonCustomOptions(Collection $products): void
    {
        $addons = $products->flatMap(function (Product $product) {
            return $product->relationLoaded('addons') ? $product->addons : collect();
        });

        $pivotIds = $addons
            ->pluck('pivot.id')
            ->filter()
            ->unique()
            ->values();

        if ($pivotIds->isEmpty()) {
            return;
        }

        $customOptions = app(ProductRepositoryInterface::class)->addonOptionOverrides($pivotIds);

        $addons->each(function ($addon) use ($customOptions) {
            $addon->setAttribute(
                'custom_option_rows',
                $customOptions->get($addon->pivot->id, collect())
            );
        });
    }
}
