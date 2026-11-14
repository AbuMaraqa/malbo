<?php

namespace Modules\Wholesale\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Store\Support\StorefrontLayout;
use Modules\Wholesale\Entities\CatalogShare;
use Modules\Wholesale\Entities\Merchant;
use Modules\Wholesale\Http\Requests\CatalogPdfRequest;
use Modules\Wholesale\Repositories\Contracts\CatalogShareRepositoryInterface;
use Modules\Wholesale\Services\CatalogPdfService;
use Modules\Wholesale\Services\CatalogShareService;
use Modules\Wholesale\Services\WholesaleCatalog;
use Modules\Wholesale\Support\PortalNav;

/**
 * The PDF catalog: the merchant's page (a category, download, share links), the download, the share
 * links, and the shared link itself, which needs no login (signed, expiring, revocable, and only
 * while the merchant is approved). Making a PDF or a link is rate limited (`wholesale-catalog`).
 */
class CatalogPdfController extends Controller
{
    public function __construct(
        private readonly CatalogPdfService $pdf,
        private readonly CatalogShareService $shareService,
        private readonly CatalogShareRepositoryInterface $shares,
        private readonly WholesaleCatalog $catalog,
        private readonly StorefrontLayout $layout,
    ) {}

    public function show(Request $request): Response
    {
        $merchant = $this->merchant($request);

        return Inertia::render('Wholesale/Portal/CatalogPdf', [
            ...$this->layout->props(['title' => __('PDF catalog'), 'noindex' => true]),
            'nav' => PortalNav::links(),
            'categories' => fn () => $this->catalog->categories(),
            'shares' => fn () => $this->shares->activeFor((int) $merchant->id)->map(fn (CatalogShare $share) => [
                'id' => $share->id,
                'url' => $share->url(),
                'category' => $share->category?->name,
                'prices' => $share->prices ?: CatalogPdfService::RETAIL,
                'expires_at' => $share->expires_at->format('Y-m-d H:i'),
                'whatsapp_url' => 'https://wa.me/?text='.rawurlencode(__('Our catalog: :url', ['url' => $share->url()])),
            ])->values()->all(),
            'maxProducts' => CatalogPdfService::LIMIT,
        ]);
    }

    public function download(CatalogPdfRequest $request): HttpResponse
    {
        $pdf = $this->pdf->pdfForDownload($this->merchant($request), $request->category(), app()->getLocale(), $request->prices());

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="catalog-'.Carbon::now()->format('Y-m-d').'.pdf"',
        ]);
    }

    public function storeShare(CatalogPdfRequest $request): RedirectResponse
    {
        $this->shareService->create($this->merchant($request), $request->category(), app()->getLocale(), $request->prices());

        return back()->with('success', __('The link is ready: it works for :days days.', ['days' => CatalogShare::DAYS]));
    }

    public function destroyShare(Request $request, int $share): RedirectResponse
    {
        $model = $this->shares->findFor((int) $this->merchant($request)->id, $share);

        abort_if($model === null, 404);

        $this->shareService->revoke($model);

        return back()->with('success', __('The link no longer works.'));
    }

    /**
     * The shared link (no login): the catalog as the merchant made it (drawn at most every 10 minutes).
     */
    public function shared(string $token): HttpResponse
    {
        $share = $this->shares->findByToken($token);

        abort_if($share === null || ! $share->isActive() || ! $share->merchant?->isApproved(), 404);
        // A link made for a category that is gone shows nothing, never the whole catalog.
        abort_if($share->category_id !== null && $share->category === null, 404);

        return response($this->pdf->pdfForShare($share), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="catalog.pdf"',
            'X-Robots-Tag' => 'noindex, nofollow',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function merchant(Request $request): Merchant
    {
        return $request->attributes->get('merchant');
    }
}
