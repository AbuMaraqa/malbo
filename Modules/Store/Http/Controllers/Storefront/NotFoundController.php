<?php

namespace Modules\Store\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\Store\Support\StorefrontLayout;
use Symfony\Component\HttpFoundation\Response;

/**
 * The 404 inside the store's layout, for an unknown address and for a product, category or page
 * that is not published. Returned, not thrown: exceptions render the app's plain error page.
 */
class NotFoundController extends Controller
{
    public function __construct(private readonly StorefrontLayout $layout) {}

    public function __invoke(Request $request): Response
    {
        return Inertia::render('Store/Storefront/NotFound', [
            ...$this->layout->props(['title' => __('Page not found'), 'noindex' => true]),
        ])->toResponse($request)->setStatusCode(404);
    }
}
