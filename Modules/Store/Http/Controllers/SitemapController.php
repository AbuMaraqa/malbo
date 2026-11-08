<?php

namespace Modules\Store\Http\Controllers;

use Illuminate\Http\Response;
use Modules\Items\Repositories\Contracts\CategoryRepositoryInterface;
use Modules\Items\Repositories\Contracts\ProductRepositoryInterface;
use Modules\Store\Repositories\Contracts\PageRepositoryInterface;

/**
 * The store's sitemap.xml: home, shop, contact, categories, products and published pages, each
 * with its Arabic and English address.
 */
class SitemapController
{
    private const LOCALES = ['ar', 'en'];

    public function __invoke(
        ProductRepositoryInterface $products,
        CategoryRepositoryInterface $categories,
        PageRepositoryInterface $pages,
    ): Response {
        $entries = [
            ['home', [], null],
            ['store.shop', [], null],
            ['store.contact', [], null],
        ];
        foreach ($categories->activeSlugs() as $slug) {
            $entries[] = ['store.category', ['slug' => $slug], null];
        }
        foreach ($products->storefrontSlugs() as $product) {
            $entries[] = ['store.product', ['slug' => $product->slug], $product->updated_at];
        }
        foreach ($pages->published() as $page) {
            $entries[] = ['store.page', ['slug' => $page->slug], $page->updated_at?->toDateTimeString()];
        }

        $urls = collect($entries)->map(fn (array $entry) => [
            'alternates' => collect(self::LOCALES)->mapWithKeys(fn (string $locale) => [$locale => route($entry[0], ['locale' => $locale, ...$entry[1]])])->all(),
            'lastmod' => $entry[2] ? substr((string) $entry[2], 0, 10) : null,
        ]);

        return response()
            ->view('store::sitemap', ['urls' => $urls], 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
