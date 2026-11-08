<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Platform\StoreService;
use App\Platform\StoreUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Support\AdminFilter;
use Modules\Core\Support\AdminTable;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Throwable;

/**
 * Platform dashboard (`/{locale}/platform`): every store with its owner and status, and the
 * actions the platform takes on a store. URLs are built with url(), not route(): the admin stays
 * on the central host they signed in on.
 */
class StoreController extends Controller
{
    public function __construct(private readonly StoreService $storeService) {}

    public function index(Request $request): Response
    {
        $table = AdminTable::for($this->storeService->tableQuery())
            ->search(['id', 'name->ar', 'name->en', 'owner_name', 'owner_email'], translated: null)
            ->filters(
                AdminFilter::exact('status'),
                AdminFilter::exact('business_type'),
                ...AdminFilter::dateRange('created_at'),
            )
            ->sorts('id', 'status', 'created_at')
            ->defaultSort('-created_at')
            ->rows(fn (Tenant $store) => $this->row($store), $request);

        return Inertia::render('Platform/Stores', [
            'table' => $table,
            'counts' => $this->storeService->counts(),
            'businessTypes' => SignUpController::businessTypes(),
            'storesUrl' => url('/'.app()->getLocale().'/platform/stores'),
        ]);
    }

    public function status(Request $request, Tenant $store): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in([Tenant::STATUS_ACTIVE, Tenant::STATUS_SUSPENDED])],
        ]);

        $this->storeService->setStatus($store, $data['status']);

        return back()->with('success', $data['status'] === Tenant::STATUS_ACTIVE
            ? __(':store is active again.', ['store' => StoreService::name($store)])
            : __(':store was suspended.', ['store' => StoreService::name($store)]));
    }

    public function openAdmin(Tenant $store): HttpResponse
    {
        // Suspended stores answer 503 before the sign-in link is read.
        if (! $store->isActive()) {
            return back()->with('error', __('This store is suspended. Activate it first.'));
        }

        $url = $this->storeService->adminSignInUrl($store, app()->getLocale());

        if ($url === null) {
            return back()->with('error', __('This store has no owner account to sign in as.'));
        }

        return Inertia::location($url);
    }

    public function destroy(Request $request, Tenant $store): RedirectResponse
    {
        $request->validate(
            ['confirm' => ['required', Rule::in([$store->id])]],
            ['confirm.in' => __('This does not match the store address.')],
        );

        $name = StoreService::name($store);

        try {
            $this->storeService->delete($store);
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', __('The store could not be deleted. Please try again.'));
        }

        return back()->with('success', __(':store was deleted.', ['store' => $name]));
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Tenant $store): array
    {
        return [
            'id' => $store->id,
            'name' => StoreService::name($store),
            'domain' => $store->primaryDomain(),
            'owner_name' => $store->owner_name,
            'owner_email' => $store->owner_email,
            'owner_phone' => $store->owner_phone,
            'business_type' => $store->business_type,
            'status' => $store->status,
            'is_active' => $store->isActive(),
            'created_at' => $store->created_at?->toDateString(),
            'store_url' => StoreUrl::for($store, '/'.app()->getLocale()),
            'imported' => $store->getInternal('imported') === true,
        ];
    }
}
