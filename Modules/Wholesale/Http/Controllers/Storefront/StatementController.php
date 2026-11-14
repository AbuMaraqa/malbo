<?php

namespace Modules\Wholesale\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Store\Support\StorefrontLayout;
use Modules\Wholesale\Entities\Merchant;
use Modules\Wholesale\Http\Requests\StatementRangeRequest;
use Modules\Wholesale\Services\MerchantStatement;
use Modules\Wholesale\Support\PortalNav;

/**
 * The merchant's statement of account (approved merchants only): its own ledger for a date range,
 * with the balance brought forward and a running balance, and the same as a PDF.
 */
class StatementController extends Controller
{
    public function __construct(
        private readonly MerchantStatement $statement,
        private readonly StorefrontLayout $layout,
    ) {}

    public function show(StatementRangeRequest $request): Response
    {
        $range = $request->range();

        return Inertia::render('Wholesale/Portal/Statement', [
            ...$this->layout->props(['title' => __('Statement of account'), 'noindex' => true]),
            'nav' => PortalNav::links(),
            'filters' => $range,
            'statement' => $this->statement->for($this->merchant($request), $range['from'], $range['to']),
            'pdfUrl' => route('store.wholesale.statement.pdf', $range),
        ]);
    }

    public function pdf(StatementRangeRequest $request): HttpResponse
    {
        $range = $request->range();

        return response($this->statement->pdf($this->merchant($request), $range['from'], $range['to'], app()->getLocale()), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="statement-'.$range['to'].'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function merchant(StatementRangeRequest $request): Merchant
    {
        return $request->attributes->get('merchant');
    }
}
