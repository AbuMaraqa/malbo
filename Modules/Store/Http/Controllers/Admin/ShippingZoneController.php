<?php

namespace Modules\Store\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Support\AdminFilter;
use Modules\Core\Support\AdminTable;
use Modules\GeoLocation\Repositories\Contracts\CityRepositoryInterface;
use Modules\Items\Support\TranslatedSort;
use Modules\Store\Entities\ShippingZone;
use Modules\Store\Http\Requests\Admin\ShippingSettingsRequest;
use Modules\Store\Http\Requests\Admin\ShippingZoneRequest;
use Modules\Store\Repositories\Contracts\ShippingZoneRepositoryInterface;
use Modules\Store\Services\ShippingZoneService;
use Modules\Store\Settings\StoreShippingSettings;
use Modules\Store\Support\Price;
use Spatie\QueryBuilder\AllowedSort;

/**
 * Shipping zones: which cities get delivery, at what fee, how fast, and from what order value it is
 * free. The store-wide free-delivery threshold is edited on the same page.
 */
class ShippingZoneController extends Controller
{
    public function __construct(
        private readonly ShippingZoneRepositoryInterface $zones,
        private readonly ShippingZoneService $zoneService,
        private readonly CityRepositoryInterface $cities,
    ) {}

    public function index(Request $request): Response
    {
        $query = $this->zones->tableQuery();

        if (! $request->filled('sort')) {
            $query->orderBy('sort_order')->orderBy('id');
        }

        return Inertia::render('Store/ShippingZone/Index', [
            'table' => AdminTable::for($query)
                ->search()
                ->filters(
                    AdminFilter::translated('name'),
                    AdminFilter::boolean('is_active'),
                    ...AdminFilter::numberRange('fee'),
                    ...AdminFilter::dateRange('created_at'),
                )
                ->sorts(AllowedSort::custom('name', new TranslatedSort), 'fee', 'eta_max_days', 'free_shipping_threshold', 'cities_count', 'sort_order', 'is_active', 'id')
                ->rows(fn (ShippingZone $zone) => [
                    'id' => $zone->id,
                    'name' => (string) $zone->name,
                    'fee' => (new Price((float) $zone->fee))->format(),
                    'eta' => $zone->etaLabel(),
                    'free_shipping_threshold' => $zone->free_shipping_threshold === null ? null : (new Price((float) $zone->free_shipping_threshold))->format(),
                    'cities_count' => (int) $zone->cities_count,
                    'sort_order' => (int) $zone->sort_order,
                    'active' => (bool) $zone->is_active,
                ]),
            'settings' => [
                'default_free_shipping_threshold' => app(StoreShippingSettings::class)->default_free_shipping_threshold,
            ],
        ]);
    }

    public function add(): Response
    {
        return Inertia::render('Store/ShippingZone/Add', ['cities' => $this->cityOptions()]);
    }

    public function store(ShippingZoneRequest $request): RedirectResponse
    {
        $this->zoneService->create($request->validated(), app()->getLocale(), $request->user()->id);

        return to_route('shipping-zones.index')->with('success', __('Shipping zone created successfully'));
    }

    public function edit(int $zone): Response
    {
        $model = $this->zones->forEdit($zone);

        return Inertia::render('Store/ShippingZone/Edit', [
            'zone' => [
                'id' => $model->id,
                'name' => $model->translate(app()->getLocale(), false)?->name ?? '',
                'fee' => $model->fee,
                'eta_min_days' => $model->eta_min_days,
                'eta_max_days' => $model->eta_max_days,
                'free_shipping_threshold' => $model->free_shipping_threshold,
                'is_active' => (bool) $model->is_active,
                'sort_order' => (int) $model->sort_order,
                'cities' => $model->cities->modelKeys(),
            ],
            'cities' => $this->cityOptions(),
        ]);
    }

    public function update(ShippingZoneRequest $request, int $zone): RedirectResponse
    {
        $this->zoneService->update($this->zones->forEdit($zone), $request->validated(), app()->getLocale());

        return to_route('shipping-zones.index')->with('success', __('Shipping zone updated successfully'));
    }

    public function status(Request $request, int $zone): RedirectResponse
    {
        $active = $request->validate(['active' => ['required', 'boolean']])['active'];

        /** @var ShippingZone $model */
        $model = $this->zones->findOrFail($zone);

        $this->zoneService->setActive($model, (bool) $active);

        return back()->with('success', __('Shipping zone updated successfully'));
    }

    public function settings(ShippingSettingsRequest $request): RedirectResponse
    {
        $value = $request->validated()['default_free_shipping_threshold'] ?? null;

        $this->zoneService->saveSettings($value === null ? null : (float) $value);

        return back()->with('success', __('Settings saved successfully'));
    }

    public function destroy(int $zone): RedirectResponse
    {
        /** @var ShippingZone $model */
        $model = $this->zones->findOrFail($zone);

        $this->zoneService->delete($model);

        return back()->with('success', __('Shipping zone deleted successfully'));
    }

    public function destroyMany(Request $request): RedirectResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];

        $this->zoneService->deleteMany($ids);

        return back()->with('success', __('Shipping zones deleted successfully'));
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    private function cityOptions(): array
    {
        return array_map(fn (array $city) => ['value' => $city['value'], 'label' => $city['label']], $this->cities->options());
    }
}
