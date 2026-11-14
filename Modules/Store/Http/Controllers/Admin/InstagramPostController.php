<?php

namespace Modules\Store\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Support\AdminFilter;
use Modules\Core\Support\AdminTable;
use Modules\Items\Entities\Product;
use Modules\Items\Repositories\Contracts\ProductRepositoryInterface;
use Modules\Store\Entities\InstagramPost;
use Modules\Store\Http\Requests\Admin\InstagramPostRequest;
use Modules\Store\Repositories\Contracts\InstagramPostRepositoryInterface;
use Modules\Store\Services\InstagramService;
use Modules\Store\Settings\StoreAppearanceSettings;
use Modules\Store\Settings\StoreContactSettings;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * Instagram content: the posts the storefront shows (entered here until a Graph API connection
 * brings them), and the home page section's switch and title.
 */
class InstagramPostController extends Controller
{
    public function __construct(
        private readonly InstagramPostRepositoryInterface $posts,
        private readonly InstagramService $instagramService,
        private readonly ProductRepositoryInterface $products,
    ) {}

    public function index(Request $request): Response
    {
        $query = $this->posts->tableQuery();

        if (! $request->filled('sort')) {
            $query->orderBy('sort_order')->orderByDesc('id');
        }

        $settings = app(StoreAppearanceSettings::class);

        return Inertia::render('Store/InstagramPost/Index', [
            'table' => AdminTable::for($query)
                ->search(['permalink', 'caption'], null)
                ->filters(
                    AdminFilter::boolean('is_active'),
                    AllowedFilter::callback('has_products', fn ($query, mixed $value) => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? $query->has('products') : $query->doesntHave('products')),
                    ...AdminFilter::dateRange('posted_at'),
                    ...AdminFilter::dateRange('created_at'),
                )
                ->sorts('sort_order', 'posted_at', 'created_at', 'id')
                ->rows(fn (InstagramPost $post) => [
                    'id' => $post->id,
                    'image' => $post->getFirstMediaUrl('image') ?: null,
                    'permalink' => $post->permalink,
                    'caption' => $post->caption,
                    'products_count' => (int) $post->products_count,
                    'sort_order' => (int) $post->sort_order,
                    'is_active' => (bool) $post->is_active,
                    'source' => $post->source,
                    'posted_at' => $post->posted_at?->toDateString(),
                ]),
            'settings' => [
                'enabled' => (bool) $settings->instagram_enabled,
                // This locale's own title: the form edits one language.
                'title' => (string) ($settings->instagram_title[app()->getLocale()] ?? ''),
                // Where "Follow us" leads: the profile set in the store's contact details.
                'profile_url' => app(StoreContactSettings::class)->instagram_url ?: null,
            ],
        ]);
    }

    public function add(): Response
    {
        return Inertia::render('Store/InstagramPost/Add');
    }

    public function store(InstagramPostRequest $request): RedirectResponse
    {
        $this->instagramService->create($request->validated(), $request->user()->id);

        return to_route('instagram-posts.index')->with('success', __('Saved Successfully'));
    }

    public function edit(int $post): Response
    {
        $model = $this->posts->forEdit($post);

        return Inertia::render('Store/InstagramPost/Edit', [
            'post' => [
                'id' => $model->id,
                'permalink' => $model->permalink,
                'caption' => $model->caption ?? '',
                'posted_at' => $model->posted_at?->format('Y-m-d\TH:i'),
                'sort_order' => (int) $model->sort_order,
                'is_active' => (bool) $model->is_active,
                'products' => $model->products->modelKeys(),
                'images' => $model->getMedia('image')->map(fn ($media) => ['id' => $media->id, 'url' => $media->getUrl()])->values(),
            ],
            'selectedProducts' => $model->products->map(fn (Product $product) => ['value' => $product->id, 'label' => (string) $product->name])->values(),
        ]);
    }

    public function update(InstagramPostRequest $request, int $post): RedirectResponse
    {
        $this->instagramService->update($this->posts->forEdit($post), $request->validated());

        return to_route('instagram-posts.index')->with('success', __('Saved Successfully'));
    }

    public function status(Request $request, int $post): RedirectResponse
    {
        $active = (bool) $request->validate(['active' => ['required', 'boolean']])['active'];

        $this->instagramService->setActive($this->posts->forEdit($post), $active);

        return back()->with('success', __('Saved Successfully'));
    }

    public function settings(Request $request): RedirectResponse
    {
        $data = $request->validate(['enabled' => ['required', 'boolean'], 'title' => ['nullable', 'string', 'max:120']]);

        $this->instagramService->saveSettings((bool) $data['enabled'], $data['title'] ?? null, app()->getLocale());

        return back()->with('success', __('Settings saved successfully'));
    }

    public function destroy(int $post): RedirectResponse
    {
        $this->instagramService->delete($this->posts->forEdit($post));

        return back()->with('success', __('Deleted successfully'));
    }

    /**
     * Products a post may show, by name or SKU, 50 at a time.
     */
    public function products(Request $request): JsonResponse
    {
        return response()->json($this->products->searchParents(trim((string) $request->query('q', '')))
            ->map(fn (Product $product) => ['value' => $product->id, 'label' => (string) $product->name])->values());
    }
}
