<?php

namespace Modules\Wholesale\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Support\AdminFilter;
use Modules\Core\Support\AdminTable;
use Modules\Items\Support\TranslatedSort;
use Modules\Wholesale\Entities\Tier;
use Modules\Wholesale\Http\Requests\Admin\TierRequest;
use Modules\Wholesale\Http\Requests\Admin\WholesaleSettingsRequest;
use Modules\Wholesale\Repositories\Contracts\TierRepositoryInterface;
use Modules\Wholesale\Services\TierService;
use Modules\Wholesale\Settings\WholesaleSettings;
use Spatie\QueryBuilder\AllowedSort;

/**
 * Wholesale price tiers, and the store-wide minimum order on the same page.
 */
class TierController extends Controller
{
    public function __construct(
        private readonly TierRepositoryInterface $tiers,
        private readonly TierService $tierService,
    ) {}

    public function index(Request $request): Response
    {
        $query = $this->tiers->tableQuery();

        if (! $request->filled('sort')) {
            $query->orderBy('sort_order')->orderBy('id');
        }

        $settings = app(WholesaleSettings::class);

        return Inertia::render('Wholesale/Tier/Index', [
            'table' => AdminTable::for($query)
                ->search()
                ->filters(
                    AdminFilter::translated('name'),
                    AdminFilter::boolean('is_default'),
                    ...AdminFilter::numberRange('discount_percent'),
                )
                ->sorts(AllowedSort::custom('name', new TranslatedSort), 'discount_percent', 'sort_order', 'merchants_count', 'id')
                ->rows(fn (Tier $tier) => [
                    'id' => $tier->id,
                    'name' => (string) $tier->name,
                    'discount_percent' => (float) $tier->discount_percent,
                    'is_default' => (bool) $tier->is_default,
                    'merchants_count' => (int) $tier->merchants_count,
                    'sort_order' => (int) $tier->sort_order,
                ]),
            'settings' => [
                'minimum_order_value' => $settings->minimum_order_value,
                'minimum_order_pieces' => $settings->minimum_order_pieces,
            ],
        ]);
    }

    public function add(): Response
    {
        return Inertia::render('Wholesale/Tier/Add');
    }

    public function store(TierRequest $request): RedirectResponse
    {
        $this->tierService->create($request->validated(), app()->getLocale(), (int) $request->user()->id);

        return to_route('wholesale-tiers.index')->with('success', __('Price tier created successfully'));
    }

    public function edit(int $tier): Response
    {
        $model = $this->tiers->forEdit($tier);

        return Inertia::render('Wholesale/Tier/Edit', [
            'tier' => [
                'id' => $model->id,
                'name' => $model->translate(app()->getLocale(), false)?->name ?? '',
                'discount_percent' => (float) $model->discount_percent,
                'sort_order' => (int) $model->sort_order,
                'is_default' => (bool) $model->is_default,
            ],
        ]);
    }

    public function update(TierRequest $request, int $tier): RedirectResponse
    {
        $this->tierService->update($this->tiers->forEdit($tier), $request->validated(), app()->getLocale());

        return to_route('wholesale-tiers.index')->with('success', __('Price tier updated successfully'));
    }

    public function destroy(int $tier): RedirectResponse
    {
        $this->tierService->delete($this->tiers->forEdit($tier));

        return back()->with('success', __('Price tier deleted successfully'));
    }

    public function settings(WholesaleSettingsRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $this->tierService->saveSettings(
            isset($data['minimum_order_value']) ? (float) $data['minimum_order_value'] : null,
            isset($data['minimum_order_pieces']) ? (int) $data['minimum_order_pieces'] : null,
        );

        return back()->with('success', __('Settings saved successfully'));
    }
}
