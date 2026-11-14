<?php

namespace Modules\Wholesale\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\GeoLocation\Repositories\Contracts\CityRepositoryInterface;
use Modules\Store\Support\StorefrontLayout;
use Modules\Wholesale\Http\Requests\MerchantApplicationRequest;
use Modules\Wholesale\Services\MerchantApplicationService;

/**
 * The public join form for shops. A signed-in visitor is sent to their account's wholesale status.
 */
class ApplyController extends Controller
{
    public function __construct(
        private readonly MerchantApplicationService $applicationService,
        private readonly CityRepositoryInterface $cities,
        private readonly StorefrontLayout $layout,
    ) {}

    public function show(Request $request): Response|RedirectResponse
    {
        if ($request->user()) {
            return to_route('store.wholesale.status');
        }

        return Inertia::render('Wholesale/Portal/Apply', [
            ...$this->layout->props(['title' => __('Become a wholesale merchant')]),
            'cities' => fn () => array_map(fn (array $city) => ['value' => $city['value'], 'label' => $city['label']], $this->cities->options()),
        ]);
    }

    public function store(MerchantApplicationRequest $request): RedirectResponse
    {
        if ($request->user()) {
            return to_route('store.wholesale.status');
        }

        $this->applicationService->apply($request->safe()->except(['register_document', 'password_confirmation']), $request->file('register_document'), $request);

        return to_route('store.wholesale.status')->with('success', __('Thank you. We received your application and will review it soon.'));
    }
}
