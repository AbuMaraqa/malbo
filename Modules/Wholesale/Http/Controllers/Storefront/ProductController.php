<?php

namespace Modules\Wholesale\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Store\Support\StorefrontLayout;
use Modules\Wholesale\Entities\Merchant;
use Modules\Wholesale\Repositories\Contracts\WholesaleProductRepositoryInterface;
use Modules\Wholesale\Services\WholesaleCartService;
use Modules\Wholesale\Services\WholesaleCatalog;
use Modules\Wholesale\Support\PortalNav;

/**
 * A product in the portal: its size-run grid, priced for the merchant's tier, with what is available
 * now and what is already in the cart. The quantities are saved with CartController::set.
 */
class ProductController extends Controller
{
    public function __construct(
        private readonly WholesaleProductRepositoryInterface $products,
        private readonly WholesaleCatalog $catalog,
        private readonly WholesaleCartService $cartService,
        private readonly StorefrontLayout $layout,
    ) {}

    public function __invoke(Request $request, string $slug): Response
    {
        /** @var Merchant $merchant */
        $merchant = $request->attributes->get('merchant');
        $product = $this->products->findPublishedBySlug($slug);
        $page = $product ? $this->catalog->page($product, $merchant, WholesaleCartService::quantities($this->cartService->current((int) $merchant->user_id))) : null;

        abort_if($page === null, 404);

        return Inertia::render('Wholesale/Portal/Product', [
            ...$this->layout->props(['title' => $page['name'], 'noindex' => true]),
            'nav' => PortalNav::links(),
            'product' => $page,
        ]);
    }
}
