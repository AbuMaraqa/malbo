<?php

namespace Modules\Store\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Items\Repositories\Contracts\ReturnRequestRepositoryInterface;
use Modules\Items\Services\ReturnRequestService;
use Modules\Store\Http\Requests\AccountReturnRequest;
use Modules\Store\Services\AccountOrders;

/**
 * A buyer asks to return or exchange items of a delivered order from its detail page. The rules
 * (their order, the window, one open request, quantities) are ReturnRequestService's.
 */
class AccountReturnController extends Controller
{
    public function __construct(
        private readonly AccountOrders $orders,
        private readonly ReturnRequestService $returnService,
        private readonly ReturnRequestRepositoryInterface $requests,
    ) {}

    public function store(AccountReturnRequest $request, string $number): RedirectResponse
    {
        $order = $this->orders->find($request->user(), $number);

        abort_if($order === null, 404);

        $this->returnService->create($order, $request->validated(), $request->user());

        return back()->with('success', __('We received your request. We will contact you about it soon.'));
    }

    /**
     * The buyer withdraws a request nobody has handled yet.
     */
    public function cancel(Request $request, string $number, int $returnRequest): RedirectResponse
    {
        $order = $this->orders->find($request->user(), $number);
        $model = $order ? $this->requests->forOrder($order->id)->firstWhere('id', $returnRequest) : null;

        abort_if($model === null, 404);

        $this->returnService->cancel($model, $request->user());

        return back()->with('success', __('Your request was cancelled.'));
    }
}
