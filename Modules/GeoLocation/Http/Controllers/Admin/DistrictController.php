<?php

namespace Modules\GeoLocation\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Locales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Support\AdminFilter;
use Modules\Core\Support\AdminTable;
use Modules\GeoLocation\Entities\District;
use Modules\GeoLocation\Enums\DistrictType;
use Modules\GeoLocation\Http\Requests\Admin\DistrictRequest;
use Modules\GeoLocation\Repositories\Contracts\CityRepositoryInterface;
use Modules\GeoLocation\Repositories\Contracts\CountryRepositoryInterface;
use Modules\GeoLocation\Repositories\Contracts\DistrictRepositoryInterface;
use Modules\GeoLocation\Repositories\Contracts\GovernorateRepositoryInterface;
use Modules\GeoLocation\Services\DistrictService;
use Modules\GeoLocation\Support\GeoFilters;

class DistrictController extends Controller
{
    public function __construct(
        private readonly DistrictRepositoryInterface $districts,
        private readonly DistrictService $districtService,
        private readonly CityRepositoryInterface $cities,
        private readonly GovernorateRepositoryInterface $governorates,
        private readonly CountryRepositoryInterface $countries,
    ) {}

    public function index(): Response
    {
        return Inertia::render('GeoLocation/District/Index', [
            'table' => AdminTable::for($this->districts->tableQuery())
                ->search()
                ->filters(
                    AdminFilter::translated('name'),
                    AdminFilter::relation('country_id', 'city.governorate', 'country_id'),
                    AdminFilter::relation('governorate_id', 'city', 'governorate_id'),
                    AdminFilter::relation('city_id', 'city'),
                    GeoFilters::integer('type'),
                    GeoFilters::locale(),
                    ...AdminFilter::dateRange('created_at'),
                )
                ->sorts(GeoFilters::nameSort(), 'type', 'created_at', 'id')
                ->rows(fn (District $district) => [
                    'id' => $district->id,
                    'name' => $district->name,
                    'city' => $district->city?->name,
                    'governorate' => $district->city?->governorate?->name,
                    'latitude' => $district->latitude,
                    'longitude' => $district->longitude,
                    'type' => $district->type?->label(),
                    'locales' => $district->translations->pluck('locale'),
                ]),
            ...$this->locationOptions(),
            'types' => $this->typeOptions(),
            'locales' => Locales::keys(),
        ]);
    }

    public function add(): Response
    {
        return Inertia::render('GeoLocation/District/Add', [
            ...$this->locationOptions(),
            'types' => $this->typeOptions(),
        ]);
    }

    public function store(DistrictRequest $request): RedirectResponse
    {
        $this->districtService->create($request->validated(), app()->getLocale());

        return to_route('districts.index')->with('success', __('District created successfully'));
    }

    public function edit(int $district): Response
    {
        /** @var District $model */
        $model = $this->districts->findOrFail($district);

        return Inertia::render('GeoLocation/District/Edit', [
            'district' => [
                'id' => $model->id,
                'name' => $model->translate(app()->getLocale(), false)?->name ?? '',
                'country_id' => $model->city?->governorate?->country_id,
                'governorate_id' => $model->city?->governorate_id,
                'city_id' => $model->city_id,
                'type' => $model->type?->value,
                'latitude' => $model->latitude,
                'longitude' => $model->longitude,
            ],
            ...$this->locationOptions(),
            'types' => $this->typeOptions(),
        ]);
    }

    public function update(DistrictRequest $request, int $district): RedirectResponse
    {
        /** @var District $model */
        $model = $this->districts->findOrFail($district);

        $this->districtService->update($model, $request->validated(), app()->getLocale());

        return to_route('districts.index')->with('success', __('District updated successfully'));
    }

    public function destroy(int $district): RedirectResponse
    {
        /** @var District $model */
        $model = $this->districts->findOrFail($district);

        $this->districtService->delete($model);

        return back()->with('success', __('District deleted successfully'));
    }

    public function destroyMany(Request $request): RedirectResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];

        $this->districtService->deleteMany($ids);

        return back()->with('success', __('Districts deleted successfully'));
    }

    /**
     * @return array{countries: list<array<string, mixed>>, governorates: list<array<string, mixed>>, cities: list<array<string, mixed>>}
     */
    private function locationOptions(): array
    {
        return [
            'countries' => $this->countries->options(),
            'governorates' => $this->governorates->options(),
            'cities' => $this->cities->options(),
        ];
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    private function typeOptions(): array
    {
        return array_map(
            fn (DistrictType $type) => ['value' => $type->value, 'label' => $type->label()],
            DistrictType::cases(),
        );
    }
}
