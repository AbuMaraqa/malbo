<?php

namespace Modules\Items\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Support\AdminFilter;
use Modules\Core\Support\AdminTable;
use Modules\Items\Entities\Tag;
use Modules\Items\Http\Requests\Admin\TagRequest;
use Modules\Items\Repositories\Contracts\TagRepositoryInterface;
use Modules\Items\Services\TagService;
use Modules\Items\Support\RecordInfo;
use Modules\Items\Support\TranslatedSort;
use Spatie\QueryBuilder\AllowedSort;

class TagController extends Controller
{
    public function __construct(
        private readonly TagRepositoryInterface $tags,
        private readonly TagService $tagService,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Items/Tag/Index', [
            'table' => AdminTable::for($this->tags->tableQuery())
                ->search(['slug'])
                ->filters(AdminFilter::translated('name'), ...AdminFilter::dateRange('created_at'))
                ->sorts(AllowedSort::custom('name', new TranslatedSort), 'slug', 'id')
                ->rows(fn (Tag $tag) => [
                    'id' => $tag->id,
                    'name' => (string) $tag->name,
                    'slug' => $tag->slug,
                    'description' => $tag->description,
                    'text_color' => $tag->text_color,
                    'background_color' => $tag->background_color,
                    'locales' => $tag->translations->pluck('locale'),
                    // The modal edits the current locale only, without the fallback the table shows.
                    'form' => [
                        'name' => $tag->translate(app()->getLocale(), false)?->name ?? '',
                        'description' => $tag->translate(app()->getLocale(), false)?->description ?? '',
                    ],
                ]),
        ]);
    }

    public function store(TagRequest $request): RedirectResponse
    {
        $this->tagService->create($request->validated(), app()->getLocale(), $request->user()->id);

        return back()->with('success', __('Tag created successfully'));
    }

    public function update(TagRequest $request, int $tag): RedirectResponse
    {
        /** @var Tag $model */
        $model = $this->tags->findOrFail($tag);

        $this->tagService->update($model, $request->validated(), app()->getLocale());

        return back()->with('success', __('Tag updated successfully'));
    }

    public function destroy(int $tag): RedirectResponse
    {
        /** @var Tag $model */
        $model = $this->tags->findOrFail($tag);

        $this->tagService->delete($model);

        return back()->with('success', __('Tag deleted successfully'));
    }

    public function destroyMany(Request $request): RedirectResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];

        $this->tagService->deleteMany($ids);

        return back()->with('success', __('Tags deleted successfully'));
    }

    public function info(int $tag): JsonResponse
    {
        return response()->json(RecordInfo::of($this->tags->findOrFail($tag)));
    }
}
