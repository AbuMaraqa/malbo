<?php

namespace Modules\Store\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Support\AdminFilter;
use Modules\Core\Support\AdminTable;
use Modules\Core\Support\Translations;
use Modules\Items\Support\TranslatedSort;
use Modules\Store\Entities\Page;
use Modules\Store\Http\Requests\Admin\PageRequest;
use Modules\Store\Repositories\Contracts\PageRepositoryInterface;
use Modules\Store\Services\PageService;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

/**
 * The store's pages: policies, FAQ and the owner's own pages. Forms edit the current locale.
 */
class PageController extends Controller
{
    private const TEXTS = ['title', 'body', 'meta_description'];

    public function __construct(
        private readonly PageRepositoryInterface $pages,
        private readonly PageService $pageService,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Store/Page/Index', [
            'table' => AdminTable::for($this->pages->tableQuery())
                ->search(['slug'], 'title')
                ->filters(
                    AdminFilter::boolean('is_published'),
                    AdminFilter::boolean('show_in_footer'),
                    AllowedFilter::callback('type', fn (Builder $query, mixed $type) => match ($type) {
                        'system' => $query->whereNotNull('key'),
                        'custom' => $query->whereNull('key'),
                        default => $query,
                    }),
                    ...AdminFilter::numberRange('sort_order'),
                    ...AdminFilter::dateRange('created_at'),
                )
                ->sorts(AllowedSort::custom('title', new TranslatedSort('title')), 'slug', 'sort_order', 'created_at', 'id')
                ->defaultSort('sort_order')
                ->rows(fn (Page $page) => [
                    'id' => $page->id,
                    'title' => (string) $page->title,
                    'slug' => $page->slug,
                    'url' => route('store.page', ['slug' => $page->slug]),
                    'is_system' => $page->isSystem(),
                    'is_published' => $page->is_published,
                    'show_in_footer' => $page->show_in_footer,
                    'sort_order' => $page->sort_order,
                    'created_at' => $page->created_at?->toDateString(),
                ]),
        ]);
    }

    public function add(): Response
    {
        return Inertia::render('Store/Page/Add');
    }

    public function store(PageRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $this->pageService->create(
            $this->attributes($data),
            Translations::fromForm($data, app()->getLocale(), self::TEXTS),
            $request->user()->id,
        );

        return to_route('pages.index')->with('success', __('Saved Successfully'));
    }

    public function edit(int $page): Response
    {
        /** @var Page $model */
        $model = $this->pages->query()->with('translations')->findOrFail($page);
        // The texts of this locale only: a missing translation starts empty, not as the other locale's text.
        $translation = $model->translate(app()->getLocale(), false);

        return Inertia::render('Store/Page/Edit', [
            'page' => [
                'id' => $model->id,
                'title' => $translation?->title ?? '',
                'body' => $translation?->body ?? '',
                'meta_description' => $translation?->meta_description ?? '',
                'slug' => $model->slug,
                'is_published' => $model->is_published,
                'show_in_footer' => $model->show_in_footer,
                'sort_order' => $model->sort_order,
            ],
            'isSystem' => $model->isSystem(),
            'viewUrl' => route('store.page', ['slug' => $model->slug]),
        ]);
    }

    public function update(PageRequest $request, int $page): RedirectResponse
    {
        /** @var Page $model */
        $model = $this->pages->query()->with('translations')->findOrFail($page);
        $data = $request->validated();

        $this->pageService->update($model, $this->attributes($data), Translations::fromForm($data, app()->getLocale(), self::TEXTS));

        return to_route('pages.index')->with('success', __('Saved Successfully'));
    }

    public function published(Request $request, int $page): RedirectResponse
    {
        /** @var Page $model */
        $model = $this->pages->findOrFail($page);

        $this->pageService->setPublished($model, $request->validate(['value' => ['required', 'boolean']])['value']);

        return back();
    }

    public function footer(Request $request, int $page): RedirectResponse
    {
        /** @var Page $model */
        $model = $this->pages->findOrFail($page);

        $this->pageService->setInFooter($model, $request->validate(['value' => ['required', 'boolean']])['value']);

        return back();
    }

    public function destroy(int $page): RedirectResponse
    {
        /** @var Page $model */
        $model = $this->pages->findOrFail($page);

        if ($model->isSystem()) {
            return back()->with('error', __('Store policies cannot be deleted. Unpublish them instead.'));
        }

        $this->pageService->delete($model);

        return back()->with('success', __('Page deleted successfully'));
    }

    /**
     * The page columns of the form.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'slug' => (string) ($data['slug'] ?? ''),
            'is_published' => (bool) ($data['is_published'] ?? false),
            'show_in_footer' => (bool) ($data['show_in_footer'] ?? false),
            'sort_order' => (int) ($data['sort_order'] ?? 100),
        ];
    }
}
