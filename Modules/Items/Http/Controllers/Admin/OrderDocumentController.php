<?php

namespace Modules\Items\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Locales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Items\Entities\Order;
use Modules\Items\Repositories\Contracts\OrderRepositoryInterface;
use Modules\Items\Services\ItemsPdfService;
use Modules\Items\Support\OrderDocument;

/**
 * An order's papers, as PDFs opened in the browser to print: the invoice (A4) and the waybill
 * (100 x 150 mm), one or many. In Arabic unless `?locale=` names another language of the site.
 */
class OrderDocumentController extends Controller
{
    /** Waybills printed at once. */
    public const MAX_WAYBILLS = 100;

    public function __construct(
        private readonly OrderRepositoryInterface $orders,
        private readonly ItemsPdfService $pdf,
    ) {}

    public function invoice(Request $request, int $order): Response
    {
        $model = $this->orders->forDocuments([$order])->first() ?? abort(404);

        return $this->pdf->invoice($model, self::locale($request));
    }

    /**
     * A courier's label, only for an order that goes to the buyer's door (OrderDocument::waybillBlocker).
     */
    public function waybill(Request $request, int $order): Response|RedirectResponse
    {
        $model = $this->orders->forDocuments([$order])->first() ?? abort(404);
        $blocker = OrderDocument::waybillBlocker($model);

        if ($blocker !== null) {
            return back()->with('error', __('Order :number gets no waybill: :reason.', ['number' => $model->order_number, 'reason' => $blocker]));
        }

        return $this->pdf->waybills([$model], self::locale($request));
    }

    /**
     * The labels of the chosen orders in one PDF. Orders that get no waybill are left out, and the
     * next page shows which and why; with none left, back with the reasons.
     */
    public function waybills(Request $request): Response|RedirectResponse
    {
        $ids = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_WAYBILLS],
            'ids.*' => ['integer', 'min:1'],
        ])['ids'];

        // Deleted and unknown ids are simply not found.
        $orders = $this->orders->forDocuments(array_values(array_unique(array_map('intval', $ids))));

        abort_if($orders->isEmpty(), 404);

        $skipped = $orders
            ->mapWithKeys(fn (Order $order) => [$order->order_number => OrderDocument::waybillBlocker($order)])
            ->filter();
        $printable = $orders->reject(fn (Order $order) => $skipped->has($order->order_number))->values();
        $message = $skipped->isEmpty() ? null : __('No waybill for: :orders.', [
            'orders' => $skipped->map(fn (string $reason, string $number) => $number.' ('.$reason.')')->implode('، '),
        ]);

        if ($printable->isEmpty()) {
            return back()->with('error', $message);
        }

        if ($message !== null) {
            // Shown on the next admin page: the PDF itself carries no message.
            $request->session()->flash('error', $message);
        }

        return $this->pdf->waybills($printable, self::locale($request));
    }

    /**
     * The `?locale=` the papers are printed in (not the admin's own language). By default the
     * site's main language, the first in config('app.locales'): Arabic. (config('app.locale')
     * already holds the request's language here.)
     */
    private static function locale(Request $request): string
    {
        $locale = $request->query('locale');

        return is_string($locale) && in_array($locale, Locales::keys(), true) ? $locale : Locales::keys()[0];
    }
}
