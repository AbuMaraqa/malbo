<?php

namespace Modules\Store\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Modules\Store\Http\Requests\StockAlertRequest;
use Modules\Store\Services\StockAlertService;

/**
 * The product page's "Notify me when available" form. Throttled (see the route); a bot that fills
 * the hidden `website` field is thanked and nothing is saved.
 */
class StockAlertController extends Controller
{
    public function __construct(private readonly StockAlertService $alerts) {}

    public function store(StockAlertRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if (blank($data['website'] ?? null)) {
            $this->alerts->subscribe((int) $data['product_id'], $data['email'] ?? null, $data['phone'] ?? null, app()->getLocale());
        }

        return back()->with('success', __('We will let you know as soon as it is back.'));
    }
}
