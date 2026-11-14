<?php

namespace Modules\Store\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\Core\Support\RichText;
use Modules\Items\Support\ProductVideo;
use Modules\Store\Repositories\Contracts\PageRepositoryInterface;
use Modules\Items\Entities\Product;
use Modules\Store\Services\BundlePage;
use Modules\Store\Services\Marketing\MetaEvents;
use Modules\Store\Services\ProductPage;
use Modules\Store\Services\ProductReviews;
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
        private readonly MetaEvents $events,
        private readonly ProductReviews $reviews,
        private readonly BundlePage $bundlePage,
    ) {}

    public function __invoke(Request $request, string $slug): Response
    {
        $product = $this->catalog->product($slug);

        if ($product === null) {
            return app(NotFoundController::class)($request);
        }

        if ($product->isBundle()) {
            return $this->bundle($request, $product);
        }

        $choice = $request->hasAny(['selected', 'addons', 'quantity']) ? $request->only(['selected', 'addons', 'quantity']) : null;
        $images = $this->productPage->images($product);
        $urls = array_column($images, 'url');
        $description = (string) $product->description;
        $category = $product->categories->first();

        return Inertia::render('Store/Storefront/ProductPage', [
            ...$this->layout->props([
                'title' => $product->name,
                'description' => $product->short_description ?: strip_tags($description),
                'og_type' => 'product',
                'og_image' => $urls[0] ?? null,
                'json_ld' => [$this->productPage->structuredData($product, $urls, $this->reviews->summary($product->id))],
            ], fn () => [$this->events->viewContent($request, $product, $this->productPage->quote($product, $choice))]),
            'product' => fn () => [
                'id' => $product->id,
                'name' => (string) $product->name,
                'short_description' => $product->short_description ?: null,
                'description' => filled($description) ? RichText::make($description)->toHtml() : null,
                'images' => $images,
                'category' => $category ? ['name' => (string) $category->name, 'url' => route('store.category', ['slug' => $category->slug])] : null,
                'details' => $this->productPage->details($product),
                'size_guide' => $this->productPage->sizeGuide($product),
                'video' => ProductVideo::embed($product->video_url),
            ],
            'badges' => fn () => $this->productPage->badges($product),
            'options' => fn () => $this->productPage->options($product),
            'quote' => fn () => $this->productPage->quote($product, $choice),
            'canAddToCart' => fn () => $this->productPage->canAddToCart($product),
            'hasWhatsapp' => fn () => filled(app(StoreContactSettings::class)->whatsapp),
            'policies' => fn () => array_map(
                fn (string $slug) => route('store.page', ['slug' => $slug]),
                array_intersect_key($this->pages->publishedKeys(), array_flip(['shipping', 'returns'])),
            ),
            // Approved reviews (a page at a time, `reviews_page`), their summary, and the form's state.
            'reviews' => fn () => $this->reviews->page($product->id),
            'reviewSummary' => fn () => $this->reviews->summary($product->id),
            'reviewForm' => fn () => $this->reviews->form($request->user(), $product),
            'related' => fn () => $this->catalog->related($product)->map(fn (ProductCard $card) => $card->toArray())->all(),
            // "Complete the look": staff's picks, a size added to the cart from here.
            'look' => fn () => $this->productPage->look($product),
        ])->toResponse($request);
    }

    /**
     * A set or bundle offer: its components and the size the buyer picks for each, its own price,
     * and what the components' stock allows. The page reloads only `quote`, with `components` and
     * `quantity` in the query string.
     */
    private function bundle(Request $request, Product $bundle): Response
    {
        $choice = $request->hasAny(['components', 'quantity']) ? $request->only(['components', 'quantity']) : null;
        $images = $this->productPage->images($bundle);
        $urls = array_column($images, 'url');
        $description = (string) $bundle->description;
        $category = $bundle->categories->first();

        return Inertia::render('Store/Storefront/BundlePage', [
            ...$this->layout->props([
                'title' => $bundle->name,
                'description' => $bundle->short_description ?: strip_tags($description),
                'og_type' => 'product',
                'og_image' => $urls[0] ?? null,
                'json_ld' => [$this->productPage->structuredData($bundle, $urls, $this->reviews->summary($bundle->id))],
            ], fn () => [$this->events->viewContent($request, $bundle, $this->bundlePage->quote($bundle, $choice))]),
            'product' => fn () => [
                'id' => $bundle->id,
                'type' => $bundle->bundle_type,
                'name' => (string) $bundle->name,
                'short_description' => $bundle->short_description ?: null,
                'description' => filled($description) ? RichText::make($description)->toHtml() : null,
                'images' => $images,
                'category' => $category ? ['name' => (string) $category->name, 'url' => route('store.category', ['slug' => $category->slug])] : null,
                'video' => ProductVideo::embed($bundle->video_url),
            ],
            'badges' => fn () => $this->productPage->badges($bundle),
            'components' => fn () => $this->bundlePage->components($bundle),
            'quote' => fn () => $this->bundlePage->quote($bundle, $choice),
            'canAddToCart' => true,
            'hasWhatsapp' => fn () => filled(app(StoreContactSettings::class)->whatsapp),
            'policies' => fn () => array_map(
                fn (string $slug) => route('store.page', ['slug' => $slug]),
                array_intersect_key($this->pages->publishedKeys(), array_flip(['shipping', 'returns'])),
            ),
            'reviews' => fn () => $this->reviews->page($bundle->id),
            'reviewSummary' => fn () => $this->reviews->summary($bundle->id),
            'reviewForm' => fn () => $this->reviews->form($request->user(), $bundle),
            'related' => fn () => $this->catalog->related($bundle)->map(fn (ProductCard $card) => $card->toArray())->all(),
        ])->toResponse($request);
    }
}
