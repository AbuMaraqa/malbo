<?php

namespace Modules\Store\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\Core\Settings\GeneralSettings;
use Modules\Store\Entities\Page;
use Modules\Store\Repositories\Contracts\PageRepositoryInterface;
use Modules\Store\Settings\StoreAppearanceSettings;
use Modules\Store\Settings\StoreContactSettings;
use Modules\Store\Support\PageOutline;
use Modules\Store\Support\PagePlaceholders;
use Modules\Store\Support\Storefront;
use Modules\Store\Support\StorefrontLayout;
use Symfony\Component\HttpFoundation\Response;

/**
 * A store page with the store's details filled in. The FAQ shows as questions that open, a long
 * page gets an "On this page" menu, and "About us" shows the store's story image and values around
 * its text. Every page links to the other help pages and to the contact page.
 */
class PageController extends Controller
{
    /** A page with at least this many sections gets the "On this page" menu. */
    private const TOC_FROM = 3;

    public function __construct(
        private readonly PageRepositoryInterface $pages,
        private readonly StorefrontLayout $layout,
    ) {}

    public function __invoke(Request $request, string $slug): Response
    {
        $page = $this->pages->findPublishedBySlug($slug);

        if ($page === null) {
            return app(NotFoundController::class)($request);
        }

        // PagePlaceholders passes the body through RichText before it is shown.
        $body = PagePlaceholders::body($page->body);
        $whatsapp = app(StoreContactSettings::class)->whatsapp;
        $common = [
            'page' => ['title' => (string) $page->title, 'lead' => $page->meta_description ?: null],
            'whatsapp' => $whatsapp,
        ];

        if ($page->key === 'about') {
            return $this->about($page, $body, $common)->toResponse($request);
        }

        $faq = null;
        $toc = [];
        $html = $body ?: null;
        $jsonLd = [];

        if ($page->key === 'faq' && $body !== '') {
            $faq = PageOutline::questions($body);
            $jsonLd[] = self::faqSchema($faq['items']);
        } elseif ($body !== '') {
            $outline = PageOutline::sections($body);
            $html = $outline['html'];
            $toc = count($outline['toc']) >= self::TOC_FROM ? $outline['toc'] : [];
        }

        return Inertia::render('Store/Storefront/PageView', [
            ...$this->layout->props(['title' => $page->title, 'description' => $page->meta_description, 'json_ld' => $jsonLd]),
            ...$common,
            'updated' => $page->updated_at?->translatedFormat('j F Y'),
            'body' => $html,
            'toc' => $toc,
            'faq' => $faq,
            'help' => $this->help($page),
        ])->toResponse($request);
    }

    /**
     * @param  array<string, mixed>  $common
     */
    private function about(Page $page, string $body, array $common): \Inertia\Response
    {
        $appearance = app(StoreAppearanceSettings::class);

        return Inertia::render('Store/Storefront/About', [
            ...$this->layout->props(['title' => $page->title, 'description' => $page->meta_description ?? Storefront::t($appearance->story_text)]),
            ...$common,
            'storeName' => (string) app(GeneralSettings::class)->site_name,
            'image' => Storefront::image($appearance->story_image_path) ?? Storefront::image($appearance->hero_image_path),
            'body' => $body ?: null,
            'values' => Storefront::values($appearance->values)->all(),
        ]);
    }

    /**
     * The other published pages, grouped for the help menu.
     *
     * @return array{about: list<array<string, mixed>>, policies: list<array<string, mixed>>}
     */
    private function help(Page $page): array
    {
        [$policies, $about] = $this->pages->published()
            ->reject(fn (Page $other) => $other->is($page))
            ->partition(fn (Page $other) => in_array($other->key, Page::POLICIES, true));

        return [
            'about' => $about->map(StorefrontLayout::pageLink(...))->values()->all(),
            'policies' => $policies->map(StorefrontLayout::pageLink(...))->values()->all(),
        ];
    }

    /**
     * @param  list<array{question: string, answer: string}>  $items
     * @return array<string, mixed>
     */
    private static function faqSchema(array $items): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(fn (array $item) => [
                '@type' => 'Question',
                'name' => $item['question'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => trim(strip_tags($item['answer']))],
            ], $items),
        ];
    }
}
