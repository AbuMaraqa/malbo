<?php

namespace Modules\Store\Support;

use Illuminate\Support\Str;
use Modules\Items\Entities\Product;
use Modules\Store\Contracts\InstagramFeedSource;
use Modules\Store\Entities\InstagramPost;
use Modules\Store\Repositories\Contracts\InstagramPostRepositoryInterface;

/**
 * The posts staff enter in the admin: active ones, in their order (then the newest), each with the
 * products it shows that are on the storefront.
 */
class ManualInstagramFeed implements InstagramFeedSource
{
    public function __construct(private readonly InstagramPostRepositoryInterface $posts) {}

    public function posts(int $limit): array
    {
        return $this->posts->forStorefront($limit)->map(fn (InstagramPost $post) => [
            'id' => (int) $post->id,
            'image' => $post->getFirstMediaUrl('image') ?: null,
            'url' => (string) $post->permalink,
            'caption' => filled($post->caption) ? Str::limit((string) $post->caption, 300) : null,
            'products' => $post->products->map(fn (Product $product) => [
                'name' => (string) $product->name,
                'url' => route('store.product', ['slug' => $product->slug]),
                'image' => $product->getFirstMediaUrl('product') ?: null,
            ])->values()->all(),
        ])->values()->all();
    }
}
