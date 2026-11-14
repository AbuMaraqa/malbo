<?php

namespace Modules\Store\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Store\Services\StorefrontCatalog;
use Modules\Store\Support\StorefrontLayout;

/**
 * Every active root category, in the admin's order, each linking to its own page. The old
 * collections links land here: collections became categories.
 */
class CategoriesController extends Controller
{
    public function __construct(
        private readonly StorefrontCatalog $catalog,
        private readonly StorefrontLayout $layout,
    ) {}

    public function __invoke(): Response
    {
        return Inertia::render('Store/Storefront/Categories', [
            ...$this->layout->props(['title' => __('Categories')]),
            'categories' => $this->catalog->categoryList(),
        ]);
    }

    /** /collections, from before the merge: the categories page, for good. */
    public function fromCollections(): RedirectResponse
    {
        return redirect()->route('store.categories', status: 301);
    }

    /** /collections/{slug}: a collection became the category with its slug. */
    public function fromCollection(string $slug): RedirectResponse
    {
        return redirect()->route('store.category', ['slug' => $slug], 301);
    }
}
