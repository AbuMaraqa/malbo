<?php

namespace Modules\Store\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Support\AdminFilter;
use Modules\Core\Support\AdminTable;
use Modules\Items\Entities\Product;
use Modules\Items\Repositories\Contracts\CategoryRepositoryInterface;
use Modules\Items\Repositories\Contracts\ProductRepositoryInterface;
use Modules\Items\Support\TranslatedSort;
use Modules\Store\Entities\CampaignBlock;
use Modules\Store\Entities\CampaignPage;
use Modules\Store\Enums\CampaignBlockType;
use Modules\Store\Http\Requests\Admin\CampaignPageRequest;
use Modules\Store\Repositories\Contracts\CampaignPageRepositoryInterface;
use Modules\Store\Services\CampaignPageService;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

/**
 * Campaign pages: their list, and a form of title, slug, publish state and dates, SEO texts and
 * blocks. Texts are edited in the current language; the storefront page doubles as the preview.
 */
class CampaignPageController extends Controller
{
    public function __construct(
        private readonly CampaignPageRepositoryInterface $pages,
        private readonly CampaignPageService $pageService,
        private readonly ProductRepositoryInterface $products,
        private readonly CategoryRepositoryInterface $categories,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Store/CampaignPage/Index', [
            'table' => AdminTable::for($this->pages->tableQuery())
                ->search(['slug'], 'title')
                ->filters(
                    AdminFilter::translated('title'),
                    AdminFilter::exact('status'),
                    AllowedFilter::callback('live', function (Builder $query, mixed $value): void {
                        $now = Carbon::now();
                        $live = fn (Builder $query) => $query->where('status', CampaignPage::PUBLISHED)
                            ->where(fn (Builder $start) => $start->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
                            ->where(fn (Builder $end) => $end->whereNull('ends_at')->orWhere('ends_at', '>=', $now));

                        filter_var($value, FILTER_VALIDATE_BOOLEAN) ? $live($query) : $query->whereNot(fn (Builder $not) => $live($not));
                    }),
                    ...AdminFilter::dateRange('starts_at'),
                    ...AdminFilter::dateRange('ends_at'),
                    ...AdminFilter::dateRange('created_at'),
                )
                ->sorts(AllowedSort::custom('title', new TranslatedSort('title')), 'slug', 'starts_at', 'ends_at', 'created_at', 'id')
                ->rows(fn (CampaignPage $page) => [
                    'id' => $page->id,
                    'title' => (string) $page->title,
                    'slug' => $page->slug,
                    'url' => route('store.campaign', ['slug' => $page->slug]),
                    'status' => $page->status,
                    'live' => $page->isLive(),
                    'starts_at' => $page->starts_at?->format('Y-m-d H:i'),
                    'ends_at' => $page->ends_at?->format('Y-m-d H:i'),
                    'blocks_count' => (int) $page->blocks_count,
                    'created_at' => $page->created_at?->toDateString(),
                ]),
        ]);
    }

    public function add(): Response
    {
        return Inertia::render('Store/CampaignPage/Add', $this->formProps());
    }

    public function store(CampaignPageRequest $request): RedirectResponse
    {
        $this->pageService->create($request->validated(), app()->getLocale(), $request->user()->id);

        return to_route('campaign-pages.index')->with('success', __('Saved Successfully'));
    }

    public function edit(int $page): Response
    {
        $model = $this->pages->withBlocks($page);
        $locale = app()->getLocale();
        $translation = $model->translate($locale, false);
        $productIds = $model->blocks->flatMap(fn (CampaignBlock $block) => (array) ($block->settings['product_ids'] ?? []))->map(fn ($id) => (int) $id)->unique()->values()->all();

        return Inertia::render('Store/CampaignPage/Edit', [
            ...$this->formProps(),
            'page' => [
                'id' => $model->id,
                'title' => $translation?->title ?? '',
                'seo_title' => $translation?->seo_title ?? '',
                'seo_description' => $translation?->seo_description ?? '',
                'slug' => $model->slug,
                'status' => $model->status,
                'starts_at' => $model->starts_at?->format('Y-m-d\TH:i'),
                'ends_at' => $model->ends_at?->format('Y-m-d\TH:i'),
                'blocks' => $model->blocks->map(fn (CampaignBlock $block) => [
                    'id' => $block->id,
                    'type' => $block->type,
                    'settings' => (object) ($block->settings ?? []),
                    // This language's texts only: another language's start empty, never copied.
                    'content' => (object) collect(CampaignBlockType::tryFrom((string) $block->type)?->texts() ?? [])
                        ->mapWithKeys(fn (string $field) => [$field => $block->text($field, $locale)])->all(),
                    'image' => $block->getFirstMediaUrl('image') ?: null,
                ])->values(),
            ],
            'selectedProducts' => $this->products->publishedByIds($productIds)->map(fn (Product $product) => ['value' => $product->id, 'label' => (string) $product->name])->values(),
            'previewUrl' => route('store.campaign', ['slug' => $model->slug]),
        ]);
    }

    public function update(CampaignPageRequest $request, int $page): RedirectResponse
    {
        $this->pageService->update($this->pages->withBlocks($page), $request->validated(), app()->getLocale());

        return to_route('campaign-pages.index')->with('success', __('Saved Successfully'));
    }

    public function destroy(int $page): RedirectResponse
    {
        $this->pageService->delete($this->pages->withBlocks($page));

        return back()->with('success', __('Deleted successfully'));
    }

    /**
     * Products a product grid may hold (sets and bundles too), 50 at a time.
     */
    public function products(Request $request): JsonResponse
    {
        return response()->json($this->products->searchParents(trim((string) $request->query('q', '')), withBundles: true)
            ->map(fn (Product $product) => ['value' => $product->id, 'label' => (string) $product->name])->values());
    }

    /**
     * @return array<string, mixed>
     */
    private function formProps(): array
    {
        return [
            'blockTypes' => CampaignBlockType::options(),
            'categories' => collect($this->categories->options())->map(fn (string $label, int $value) => ['value' => $value, 'label' => $label])->values(),
        ];
    }
}
