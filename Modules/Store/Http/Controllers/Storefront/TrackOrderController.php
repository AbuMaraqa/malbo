<?php

namespace Modules\Store\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Store\Http\Requests\TrackOrderRequest;
use Modules\Store\Services\OrderTracking;
use Modules\Store\Support\StorefrontLayout;

/**
 * "Track your order": the order number and the phone, sent by POST so the phone never sits in an
 * address. The result is rendered in the same response; nothing about the order is kept.
 */
class TrackOrderController extends Controller
{
    public function __construct(
        private readonly OrderTracking $tracking,
        private readonly StorefrontLayout $layout,
    ) {}

    public function show(Request $request): Response
    {
        $number = $request->query('number');

        return $this->page(is_string($number) ? mb_substr(trim($number), 0, 40) : '', null);
    }

    public function find(TrackOrderRequest $request): Response
    {
        $data = $request->validated();

        return $this->page(trim($data['number']), $this->tracking->lookup((string) $request->ip(), $data['number'], $data['phone']));
    }

    /**
     * @param  array<string, mixed>|null  $result
     */
    private function page(string $number, ?array $result): Response
    {
        return Inertia::render('Store/Storefront/TrackOrder', [
            ...$this->layout->props(['title' => __('Track your order'), 'noindex' => true]),
            'number' => $number,
            'result' => $result,
        ]);
    }
}
