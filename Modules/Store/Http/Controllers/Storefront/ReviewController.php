<?php

namespace Modules\Store\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Store\Http\Requests\ReviewRequest;
use Modules\Store\Services\ProductReviews;
use Modules\Store\Services\ReviewService;
use Modules\Store\Services\StorefrontCatalog;
use Modules\Store\Support\StorefrontLayout;

/**
 * A buyer's review from the product page (signed in, throttled), and "My reviews" in the account.
 */
class ReviewController extends Controller
{
    public function __construct(
        private readonly ReviewService $reviewService,
        private readonly ProductReviews $reviews,
        private readonly StorefrontCatalog $catalog,
        private readonly StorefrontLayout $layout,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Store/Storefront/Account/Reviews', [
            ...$this->layout->props(['title' => __('My reviews'), 'noindex' => true]),
            'reviews' => fn () => $this->reviews->forUser((int) $request->user()->id),
        ]);
    }

    public function store(ReviewRequest $request, string $slug): RedirectResponse
    {
        $product = $this->catalog->product($slug);
        abort_if($product === null, 404);

        $data = $request->validated();
        $this->reviewService->submit($request->user(), $product, $data, $request->file('photos', []));

        return back()->with('success', __('Thank you. Your review will appear once we check it.'));
    }
}
