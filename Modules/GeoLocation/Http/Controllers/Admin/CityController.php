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
use Modules\GeoLocation\Entities\City;
use Modules\GeoLocation\Enums\CapitalType;
use Modules\GeoLocation\Enums\CityType;
use Modules\GeoLocation\Http\Requests\Admin\CityRequest;
use Modules\GeoLocation\Repositories\Contracts\CityRepositoryInterface;
use Modules\GeoLocation\Repositories\Contracts\CountryRepositoryInterface;
use Modules\GeoLocation\Repositories\Contracts\GovernorateRepositoryInterface;
use Modules\GeoLocation\Services\CityService;
use Modules\GeoLocation\Support\GeoFilters;

class CityController extends Controller
{
    public function __construct(
        private readonly CityRepositoryInterface $cities,
        private readonly CityService $cityService,
        private readonly GovernorateRepositoryInterface $governorates,
        private readonly CountryRepositoryInterface $countries,
    ) {}

    public function index(): Response
    {
        return Inertia::render('GeoLocation/City/Index', [
            'table' => AdminTable::for($this->cities->tableQuery())
                ->search()
                ->filters(
                    AdminFilter::translated('name'),
                    AdminFilter::relation('country_id', 'governorate', 'country_id'),
                    AdminFilter::relation('governorate_id', 'governorate'),
                    GeoFilters::integer('type'),
                    AdminFilter::boolean('is_capital'),
                    GeoFilters::integer('capital_type'),
                    GeoFilters::locale(),
                    ...GeoFilters::integerRange('population'),
                    ...AdminFilter::dateRange('created_at'),
                )
                ->sorts(GeoFilters::nameSort(), 'population', 'type', 'created_at', 'id')
                ->rows(fn (City $city) => [
                    'id' => $city->id,
                    'name' => $city->name,
                    'governorate' => $city->governorate?->name,
                    'country' => $city->governorate?->country?->name,
                    'latitude' => $city->latitude,
                    'longitude' => $city->longitude,
                    'population' => $city->population,
                    'type' => $city->type?->label(),
                    'is_capital' => (bool) $city->is_capital,
                    'capital_type' => $city->capital_type?->label(),
                    'locales' => $city->translations->pluck('locale'),
                ]),
            'countries' => $this->countries->options(),
            'governorates' => $this->governorates->options(),
            'types' => $this->enumOptions(CityType::options()),
            'capitalTypes' => $this->enumOptions(CapitalType::options()),
            'locales' => Locales::keys(),
        ]);
    }

    public function add(): Response
    {
        return Inertia::render('GeoLocation/City/Add', $this->formOptions());
    }

    public function store(CityRequest $request): RedirectResponse
    {
        $this->cityService->create($request->validated(), app()->getLocale());

        return to_route('cities.index')->with('success', __('City created successfully'));
    }

    public function edit(int $city): Response
    {
        /** @var City $model */
        $model = $this->cities->findOrFail($city);

        return Inertia::render('GeoLocation/City/Edit', [
            'city' => [
                'id' => $model->id,
                'name' => $model->translate(app()->getLocale(), false)?->name ?? '',
                'country_id' => $model->governorate?->country_id,
                'governorate_id' => $model->governorate_id,
                'population' => $model->population,
                'latitude' => $model->latitude,
                'longitude' => $model->longitude,
                'type' => $model->type?->value,
                'is_capital' => (bool) $model->is_capital,
                'capital_type' => $model->capital_type?->value,
            ],
            ...$this->formOptions(),
        ]);
    }

    public function update(CityRequest $request, int $city): RedirectResponse
    {
        /** @var City $model */
        $model = $this->cities->findOrFail($city);

        $this->cityService->update($model, $request->validated(), app()->getLocale());

        return to_route('cities.index')->with('success', __('City updated successfully'));
    }

    public function destroy(int $city): RedirectResponse
    {
        /** @var City $model */
        $model = $this->cities->findOrFail($city);

        $this->cityService->delete($model);

        return back()->with('success', __('City deleted successfully'));
    }

    public function destroyMany(Request $request): RedirectResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];

        $this->cityService->deleteMany($ids);

        return back()->with('success', __('Cities deleted successfully'));
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'countries' => $this->countries->options(),
            'governorates' => $this->governorates->options(),
            'types' => $this->enumOptions(CityType::options()),
            'capitalTypes' => $this->enumOptions(CapitalType::options()),
        ];
    }

    /**
     * @param  array<int, string>  $options
     * @return list<array{value: int, label: string}>
     */
    private function enumOptions(array $options): array
    {
        return array_map(
            fn (int $value, string $label) => ['value' => $value, 'label' => $label],
            array_keys($options),
            $options,
        );
    }
}
