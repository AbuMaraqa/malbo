<?php

namespace Modules\Store\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Store\Entities\Address;
use Modules\Store\Http\Requests\AddressRequest;
use Modules\Store\Repositories\Contracts\AddressRepositoryInterface;
use Modules\Store\Repositories\Contracts\ShippingZoneRepositoryInterface;
use Modules\Store\Services\AddressService;
use Modules\Store\Support\StorefrontLayout;

/**
 * The account's saved addresses. Another buyer's address is a 404.
 */
class AddressController extends Controller
{
    public function __construct(
        private readonly AddressRepositoryInterface $addresses,
        private readonly AddressService $service,
        private readonly ShippingZoneRepositoryInterface $zones,
        private readonly StorefrontLayout $layout,
    ) {}

    public function index(Request $request): Response
    {
        $userId = (int) $request->user()->id;

        return Inertia::render('Store/Storefront/Account/Addresses', [
            ...$this->layout->props(['title' => __('My addresses'), 'noindex' => true]),
            'addresses' => fn () => $this->addresses->forUser($userId)->map(fn (Address $address) => self::row($address))->values()->all(),
            'cities' => fn () => $this->zones->deliverableCities(),
            'limit' => AddressService::LIMIT,
        ]);
    }

    public function store(AddressRequest $request): RedirectResponse
    {
        $this->service->create($request->user(), $request->validated());

        return back()->with('success', __('The address was saved.'));
    }

    public function update(AddressRequest $request, int $address): RedirectResponse
    {
        $this->service->update($this->addresses->findForUser((int) $request->user()->id, $address), $request->validated());

        return back()->with('success', __('The address was saved.'));
    }

    public function makeDefault(Request $request, int $address): RedirectResponse
    {
        $this->service->makeDefault($this->addresses->findForUser((int) $request->user()->id, $address));

        return back()->with('success', __('This is now your default address.'));
    }

    public function destroy(Request $request, int $address): RedirectResponse
    {
        $this->service->delete($this->addresses->findForUser((int) $request->user()->id, $address));

        return back()->with('success', __('The address was deleted.'));
    }

    /**
     * @return array<string, mixed>
     */
    public static function row(Address $address): array
    {
        return [
            'id' => $address->id,
            'label' => $address->label,
            'recipient_name' => $address->recipient_name,
            'phone' => $address->phone,
            'city_id' => $address->city_id,
            'city' => $address->city ? (string) $address->city->name : null,
            'district_id' => $address->district_id,
            'district' => $address->district ? (string) $address->district->name : null,
            'address_line' => $address->address_line,
            'is_default' => $address->is_default,
        ];
    }
}
