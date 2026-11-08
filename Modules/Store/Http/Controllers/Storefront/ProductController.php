<?php

namespace Modules\Store\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\Core\Support\RichText;
use Modules\Store\Repositories\Contracts\PageRepositoryInterface;
use Modules\Store\Services\ProductPage;
use Modules\Store\Services\StorefrontCatalog;
use Modules\Store\Settings\StoreContactSettings;
use Modules\Store\Support\ProductCard;
use Modules\Store\Support\StorefrontLayout;
use Symfony\Component\HttpFoundation\Response;

/**
 * A product: gallery, price, variation and addon choices, quantity, related products, and "Order
 * on WhatsApp". The page reloads only `quote` with the buyer's choice in the query string; every
 * price and total comes from the server.
 */
class ProductController extends Controller
{
    public function __construct(
        private readonly StorefrontCatalog $catalog,
        private readonly ProductPage $productPage,
        private readonly PageRepositoryInterface $pages,
        private readonly StorefrontLayout $layout,
    ) {}

    public function __invoke(Request $request, string $slug): Response
    {
        $product = $this->catalog->product($slug);

        if ($product === null) {
            return app(NotFoundController::class)($request);
        }

        $choice = $request->hasAny(['selected', 'addons', 'quantity']) ? $request->only(['selected', 'addons', 'quantity']) : null;
        $images = $this->productPage->images($product);
        $description = (string) $product->description;
        $category = $product->categories->first();

        return Inertia::render('Store/Storefront/ProductPage', [
            ...$this->layout->props([
                'title' => $product->name,
                'description' => $product->short_description ?: strip_tags($description),
                'og_type' => 'product',
                'og_image' => $images[0] ?? null,
                'json_ld' => [$this->productPage->structuredData($product, $images)],
            ]),
            'product' => fn () => [
                'id' => $product->id,
                'name' => (string) $product->name,
                'short_description' => $product->short_description ?: null,
                'description' => filled($description) ? RichText::make($description)->toHtml() : null,
                'images' => $images,
                'category' => $category ? ['name' => (string) $category->name, 'url' => route('store.category', ['slug' => $category->slug])] : null,
            ],
            'options' => fn () => $this->productPage->options($product),
            'quote' => fn () => $this->productPage->quote($product, $choice),
            'hasWhatsapp' => fn () => filled(app(StoreContactSettings::class)->whatsapp),
            'policies' => fn () => array_map(
                fn (string $slug) => route('store.page', ['slug' => $slug]),
                array_intersect_key($this->pages->publishedKeys(), array_flip(['shipping', 'returns'])),
            ),
            'related' => fn () => $this->catalog->related($product)->map(fn (ProductCard $card) => $card->toArray())->all(),
        ])->toResponse($request);
    }
}
