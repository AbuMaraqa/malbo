<?php

namespace Modules\Store\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\Rule;
use Modules\Store\Support\MarketingConsent;

/**
 * The marketing cookies banner's answer, kept in a cookie (MarketingConsent). The page then reloads
 * its `marketing` prop: with `granted`, the Pixel loads and this visit's events go out.
 */
class ConsentController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $choice = $request->validate([
            'choice' => ['required', 'string', Rule::in([MarketingConsent::GRANTED, MarketingConsent::DENIED])],
        ])['choice'];

        MarketingConsent::queue($choice);

        // Taken back: the Pixel's own cookies go too (the page also clears them on the store's domain).
        if ($choice === MarketingConsent::DENIED) {
            foreach (['_fbp', '_fbc'] as $cookie) {
                Cookie::queue(Cookie::forget($cookie));
            }
        }

        return back();
    }
}
