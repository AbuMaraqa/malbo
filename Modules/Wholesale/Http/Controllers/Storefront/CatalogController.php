<?php

namespace Modules\Wholesale\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Store\Support\StorefrontLayout;
use Modules\Wholesale\Entities\Merchant;
use Modules\Wholesale\Services\WholesaleCatalog;
use Modules\Wholesale\Support\PortalNav;

/**
 * The wholesale catalog (approved merchants only: `merchant.approved`).
 */
class CatalogController extends Controller
{
    public function __construct(
        private readonly WholesaleCatalog $catalog,
        private readonly StorefrontLayout $layout,
    ) {}

    public function __invoke(Request $request): Response
    {
        /** @var Merchant $merchant */
        $merchant = $request->attributes->get('merchant');
        $filters = [
            'q' => is_string($request->query('q')) ? mb_substr(trim($request->query('q')), 0, 100) : null,
            'category' => is_string($request->query('category')) ? mb_substr($request->query('category'), 0, 200) : null,
        ];

        return Inertia::render('Wholesale/Portal/Catalog', [
            ...$this->layout->props(['title' => __('Wholesale catalog'), 'noindex' => true]),
            'nav' => PortalNav::links(),
            'shop' => $merchant->shop_name,
            'filters' => $filters,
            'categories' => fn () => $this->catalog->categories(),
            'products' => fn () => $this->catalog->listing($merchant, $filters),
        ]);
    }
}
