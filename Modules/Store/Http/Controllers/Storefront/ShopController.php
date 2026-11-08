<?php

namespace Modules\Store\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Store\Services\StorefrontShop;
use Modules\Store\Support\StorefrontLayout;

/**
 * The shop grid: every product, with search, sort and pagination kept in the URL.
 */
class ShopController extends Controller
{
    public function __construct(
        private readonly StorefrontShop $shop,
        private readonly StorefrontLayout $layout,
    ) {}

    public function __invoke(Request $request): Response
    {
        return Inertia::render('Store/Storefront/Shop', [
            ...$this->layout->props(['title' => __('All products')]),
            ...$this->shop->props($request),
        ]);
    }
}
