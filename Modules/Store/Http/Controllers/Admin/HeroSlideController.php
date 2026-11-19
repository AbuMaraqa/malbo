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
use Modules\Store\Entities\HeroSlide;
use Modules\Store\Http\Requests\Admin\HeroSlideRequest;
use Modules\Store\Repositories\Contracts\HeroSlideRepositoryInterface;
use Modules\Store\Services\HeroSlideService;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * The home page slider: its slides (a picture, optional texts and a link), in their order, each
 * with a switch and optional dates. Texts are edited in the current language.
 */
class HeroSlideController extends Controller
{
    public function __construct(
        private readonly HeroSlideRepositoryInterface $slides,
        private readonly HeroSlideService $slideService,
    ) {}

    public function index(Request $request): Response
    {
        $query = $this->slides->tableQuery();

        if (! $request->filled('sort')) {
            $query->orderBy('sort_order')->orderByDesc('id');
        }

        return Inertia::render('Store/HeroSlide/Index', [
            'table' => AdminTable::for($query)
                ->search(['link'], 'title')
                ->filters(
                    AdminFilter::boolean('is_active'),
                    AllowedFilter::callback('live', fn (Builder $query, mixed $value) => $this->slides->whereLive($query, filter_var($value, FILTER_VALIDATE_BOOLEAN))),
                    ...AdminFilter::dateRange('starts_at'),
                    ...AdminFilter::dateRange('ends_at'),
                    ...AdminFilter::dateRange('created_at'),
                )
                ->sorts('sort_order', 'starts_at', 'ends_at', 'created_at', 'id')
                ->rows(fn (HeroSlide $slide) => [
                    'id' => $slide->id,
                    'image' => $slide->getFirstMediaUrl('image') ?: null,
                    'title' => filled($slide->title) ? (string) $slide->title : null,
                    'subtitle' => filled($slide->subtitle) ? (string) $slide->subtitle : null,
                    'link' => $slide->link,
                    'sort_order' => (int) $slide->sort_order,
                    'is_active' => (bool) $slide->is_active,
                    'live' => $slide->isLive(),
                    'starts_at' => $slide->starts_at?->format('Y-m-d H:i'),
                    'ends_at' => $slide->ends_at?->format('Y-m-d H:i'),
                ]),
            'previewUrl' => route('home'),
        ]);
    }

    public function add(): Response
    {
        return Inertia::render('Store/HeroSlide/Add');
    }

    public function store(HeroSlideRequest $request): RedirectResponse
    {
        $this->slideService->create($request->validated(), app()->getLocale(), $request->user()->id);

        return to_route('hero-slides.index')->with('success', __('Saved Successfully'));
    }

    public function edit(int $slide): Response
    {
        $model = $this->slides->forEdit($slide);
        // This language's texts only: another language's start empty, never copied.
        $translation = $model->translate(app()->getLocale(), false);
        $images = fn (string $collection) => $model->getMedia($collection)->map(fn ($media) => ['id' => $media->id, 'url' => $media->getUrl()])->values();

        return Inertia::render('Store/HeroSlide/Edit', [
            'slide' => [
                'id' => $model->id,
                'eyebrow' => $translation?->eyebrow ?? '',
                'title' => $translation?->title ?? '',
                'subtitle' => $translation?->subtitle ?? '',
                'button_label' => $translation?->button_label ?? '',
                'link' => $model->link ?? '',
                'sort_order' => (int) $model->sort_order,
                'is_active' => (bool) $model->is_active,
                'starts_at' => $model->starts_at?->format('Y-m-d\TH:i'),
                'ends_at' => $model->ends_at?->format('Y-m-d\TH:i'),
                'images' => $images('image'),
                'mobile_images' => $images('mobile_image'),
            ],
        ]);
    }

    public function update(HeroSlideRequest $request, int $slide): RedirectResponse
    {
        $this->slideService->update($this->slides->forEdit($slide), $request->validated(), app()->getLocale());

        return to_route('hero-slides.index')->with('success', __('Saved Successfully'));
    }

    public function status(Request $request, int $slide): RedirectResponse
    {
        $active = (bool) $request->validate(['active' => ['required', 'boolean']])['active'];

        $this->slideService->setActive($this->slides->forEdit($slide), $active);

        return back()->with('success', __('Saved Successfully'));
    }

    public function destroy(int $slide): RedirectResponse
    {
        $this->slideService->delete($this->slides->forEdit($slide));

        return back()->with('success', __('Deleted successfully'));
    }
}
