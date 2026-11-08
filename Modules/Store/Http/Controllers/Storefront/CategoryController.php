<?php

namespace Modules\Store\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\Store\Services\StorefrontCatalog;
use Modules\Store\Services\StorefrontShop;
use Modules\Store\Support\StorefrontLayout;
use Symfony\Component\HttpFoundation\Response;

/**
 * The shop grid of one category, its children included.
 */
class CategoryController extends Controller
{
    public function __construct(
        private readonly StorefrontCatalog $catalog,
        private readonly StorefrontShop $shop,
        private readonly StorefrontLayout $layout,
    ) {}

    public function __invoke(Request $request, string $slug): Response
    {
        $category = $this->catalog->category($slug);

        if ($category === null) {
            return app(NotFoundController::class)($request);
        }

        return Inertia::render('Store/Storefront/Category', [
            ...$this->layout->props(['title' => $category->name]),
            ...$this->shop->props($request, $category),
        ])->toResponse($request);
    }
}
