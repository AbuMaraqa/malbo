<?php

namespace Modules\Core\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Entities\Currency;
use Modules\Core\Enums\SymbolPosition;
use Modules\Core\Http\Requests\Admin\CurrencyRequest;
use Modules\Core\Repositories\Contracts\CurrencyRepositoryInterface;
use Modules\Core\Services\CurrencyService;
use Modules\Core\Support\AdminFilter;
use Modules\Core\Support\AdminTable;

class CurrencyController extends Controller
{
    public function __construct(
        private readonly CurrencyRepositoryInterface $currencies,
        private readonly CurrencyService $currencyService,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Core/Currency/Index', [
            'table' => AdminTable::for($this->currencies->tableQuery())
                ->search(['code', 'symbol'])
                ->filters(AdminFilter::boolean('is_active'), AdminFilter::exact('symbol_position'), ...AdminFilter::dateRange('created_at'), ...AdminFilter::numberRange('exchange_rate'))
                ->sorts('code', 'symbol', 'exchange_rate', 'id')
                ->rows(fn (Currency $currency) => [
                    'id' => $currency->id,
                    'name' => $currency->name,
                    'code' => $currency->code,
                    'symbol' => $currency->symbol,
                    'exchange_rate' => $currency->exchange_rate,
                    'is_active' => (bool) $currency->is_active,
                    'locales' => $currency->translations->pluck('locale'),
                ]),
        ]);
    }

    public function add(): Response
    {
        return Inertia::render('Core/Currency/Add', [
            'symbolPositions' => $this->symbolPositions(),
        ]);
    }

    public function store(CurrencyRequest $request): RedirectResponse
    {
        $this->currencyService->create($request->validated(), app()->getLocale(), $request->user()->id);

        return to_route('currencies.index')->with('success', __('Currency created successfully'));
    }

    public function edit(int $currency): Response
    {
        /** @var Currency $model */
        $model = $this->currencies->findOrFail($currency);

        return Inertia::render('Core/Currency/Edit', [
            'currency' => [
                'id' => $model->id,
                'name' => $model->translate(app()->getLocale(), false)?->name ?? '',
                'plural_name' => $model->translate(app()->getLocale(), false)?->plural_name ?? '',
                'code' => $model->code,
                'symbol' => $model->symbol,
                'symbol_position' => $model->symbol_position,
                'decimal_places' => $model->decimal_places,
                'exchange_rate' => $model->exchange_rate,
                'is_active' => (bool) $model->is_active,
            ],
            'symbolPositions' => $this->symbolPositions(),
        ]);
    }

    public function update(CurrencyRequest $request, int $currency): RedirectResponse
    {
        /** @var Currency $model */
        $model = $this->currencies->findOrFail($currency);

        $this->currencyService->update($model, $request->validated(), app()->getLocale());

        return to_route('currencies.index')->with('success', __('Currency updated successfully'));
    }

    public function destroy(int $currency): RedirectResponse
    {
        /** @var Currency $model */
        $model = $this->currencies->findOrFail($currency);

        $this->currencyService->delete($model);

        return back()->with('success', __('Currency deleted successfully'));
    }

    public function destroyMany(Request $request): RedirectResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];

        $this->currencyService->deleteMany($ids);

        return back()->with('success', __('Currencies deleted successfully'));
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function symbolPositions(): array
    {
        return array_map(
            fn (SymbolPosition $position) => ['value' => $position->value, 'label' => __(ucfirst($position->value))],
            SymbolPosition::cases(),
        );
    }
}
