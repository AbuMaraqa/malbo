<?php

namespace Modules\Store\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Store\Entities\MarketingOptOut;
use Modules\Store\Services\OptOutService;
use Modules\Store\Support\StorefrontLayout;

/**
 * "Stop these messages", from a cart reminder or a stock alert (signed links, see the routes). The
 * page asks first, so a mail scanner opening the link changes nothing; the button posts to the same
 * signed address.
 */
class UnsubscribeController extends Controller
{
    public function __construct(
        private readonly OptOutService $optOuts,
        private readonly StorefrontLayout $layout,
    ) {}

    public function show(Request $request): Response
    {
        $contact = $this->contact($request);

        return Inertia::render('Store/Storefront/Unsubscribe', [
            ...$this->layout->props(['title' => __('Stop these messages'), 'noindex' => true]),
            'contact' => OptOutService::mask($contact['channel'], $contact['value']),
            'done' => $contact['channel'] === MarketingOptOut::EMAIL
                ? $this->optOuts->optedOut($contact['value'], null)
                : $this->optOuts->optedOut(null, $contact['value']),
            'confirmUrl' => $request->fullUrl(),
        ]);
    }

    public function confirm(Request $request): RedirectResponse
    {
        $contact = $this->contact($request);

        $this->optOuts->optOut($contact['channel'], $contact['value']);

        return redirect()->to($request->fullUrl())->with('success', __('Done. You will not get these messages any more.'));
    }

    /**
     * @return array{channel: string, value: string}
     */
    private function contact(Request $request): array
    {
        $contact = $this->optOuts->decode($request->query('c'));

        abort_if($contact === null, 404);

        return $contact;
    }
}
