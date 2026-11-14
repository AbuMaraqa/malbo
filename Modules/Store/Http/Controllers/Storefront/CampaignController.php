<?php

namespace Modules\Store\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\Store\Repositories\Contracts\CampaignPageRepositoryInterface;
use Modules\Store\Services\CampaignPageView;
use Modules\Store\Support\StorefrontLayout;
use Symfony\Component\HttpFoundation\Response;

/**
 * A campaign page. Public while published and inside its dates; anyone else gets the store's 404.
 * Staff who may list campaign pages see it any time, as a preview: marked, never indexed, and no
 * Meta event is built for it.
 */
class CampaignController extends Controller
{
    public function __construct(
        private readonly CampaignPageRepositoryInterface $pages,
        private readonly CampaignPageView $view,
        private readonly StorefrontLayout $layout,
    ) {}

    public function __invoke(Request $request, string $slug): Response
    {
        $page = $this->pages->findBySlug($slug);
        $preview = $page !== null && ! $page->isLive() && (bool) $request->user()?->can('Campaign Page View List');

        if ($page === null || (! $page->isLive() && ! $preview)) {
            return app(NotFoundController::class)($request);
        }

        $title = (string) $page->title;

        return Inertia::render('Store/Storefront/Campaign', [
            ...$this->layout->props([
                'title' => filled($page->seo_title) ? (string) $page->seo_title : $title,
                'description' => filled($page->seo_description) ? (string) $page->seo_description : null,
                'noindex' => $preview,
            ]),
            // A preview builds no PageView: staff looking at a draft are no visitors.
            ...($preview ? ['marketing' => null] : []),
            'campaign' => [
                'title' => $title,
                'preview' => $preview,
                'ends_at' => $page->ends_at?->toIso8601String(),
            ],
            'blocks' => fn () => $this->view->blocks($page),
        ])->toResponse($request);
    }
}
