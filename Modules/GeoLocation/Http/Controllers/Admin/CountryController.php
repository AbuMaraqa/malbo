<?php

namespace Modules\GeoLocation\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Locales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Entities\Currency;
use Modules\Core\Repositories\Contracts\CurrencyRepositoryInterface;
use Modules\Core\Support\AdminFilter;
use Modules\Core\Support\AdminTable;
use Modules\GeoLocation\Entities\Country;
use Modules\GeoLocation\Http\Requests\Admin\CountryRequest;
use Modules\GeoLocation\Repositories\Contracts\CountryRepositoryInterface;
use Modules\GeoLocation\Services\CountryService;
use Modules\GeoLocation\Support\GeoFilters;

class CountryController extends Controller
{
    public function __construct(
        private readonly CountryRepositoryInterface $countries,
        private readonly CountryService $countryService,
        private readonly CurrencyRepositoryInterface $currencies,
    ) {}

    public function index(): Response
    {
        return Inertia::render('GeoLocation/Country/Index', [
            'table' => AdminTable::for($this->countries->tableQuery())
                ->search(['code', 'phone_code'])
                ->filters(
                    AdminFilter::translated('name'),
                    AdminFilter::exact('code'),
                    AdminFilter::exact('phone_code'),
                    AdminFilter::relation('currency_id', 'currency'),
                    GeoFilters::locale(),
                    ...AdminFilter::dateRange('created_at'),
                )
                ->sorts(GeoFilters::nameSort(), 'code', 'phone_code', 'created_at', 'id')
                ->rows(fn (Country $country) => [
                    'id' => $country->id,
                    'name' => $country->name,
                    'code' => $country->code,
                    'phone_code' => $country->phone_code,
                    'currency' => $country->currency ? $country->currency->name.' ('.$country->currency->symbol.')' : null,
                    'locales' => $country->translations->pluck('locale'),
                ]),
            'codes' => $this->countries->distinctValues('code'),
            'phoneCodes' => $this->countries->distinctValues('phone_code'),
            'currencies' => $this->currencyOptions(),
            'locales' => Locales::keys(),
        ]);
    }

    public function add(): Response
    {
        return Inertia::render('GeoLocation/Country/Add', [
            'currencies' => $this->currencyOptions(),
        ]);
    }

    public function store(CountryRequest $request): RedirectResponse
    {
        $this->countryService->create($request->validated(), app()->getLocale());

        return to_route('countries.index')->with('success', __('Country created successfully'));
    }

    public function edit(int $country): Response
    {
        /** @var Country $model */
        $model = $this->countries->findOrFail($country);

        return Inertia::render('GeoLocation/Country/Edit', [
            'country' => [
                'id' => $model->id,
                'name' => $model->translate(app()->getLocale(), false)?->name ?? '',
                'code' => $model->code,
                'phone_code' => $model->phone_code,
                'currency_id' => $model->currency_id,
            ],
            'currencies' => $this->currencyOptions(),
        ]);
    }

    public function update(CountryRequest $request, int $country): RedirectResponse
    {
        /** @var Country $model */
        $model = $this->countries->findOrFail($country);

        $this->countryService->update($model, $request->validated(), app()->getLocale());

        return to_route('countries.index')->with('success', __('Country updated successfully'));
    }

    public function destroy(int $country): RedirectResponse
    {
        /** @var Country $model */
        $model = $this->countries->findOrFail($country);

        $this->countryService->delete($model);

        return back()->with('success', __('Country deleted successfully'));
    }

    public function destroyMany(Request $request): RedirectResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];

        $this->countryService->deleteMany($ids);

        return back()->with('success', __('Countries deleted successfully'));
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    private function currencyOptions(): array
    {
        return $this->currencies->query()->with('translations')->get()
            ->map(fn (Currency $currency) => ['value' => $currency->id, 'label' => $currency->name.' ('.$currency->symbol.')'])
            ->sortBy('label')
            ->values()
            ->all();
    }
}
