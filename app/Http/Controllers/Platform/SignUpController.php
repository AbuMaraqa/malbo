<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Platform\Provisioning\NewStoreData;
use App\Platform\Provisioning\SlugAvailability;
use App\Platform\Provisioning\StoreProvisioner;
use App\Platform\Provisioning\StoreProvisioningFailed;
use App\Platform\StoreUrl;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Store\Services\StoreAppearanceService;
use Modules\Store\Theme\BrandColor;
use Modules\Store\Theme\FontCatalog;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Public store sign-up (`/{locale}/register-store` on the platform host): the visitor picks a
 * store name and address, becomes its owner, chooses a look, and lands signed in on the new
 * store's admin.
 *
 * Limited per address (rate limiter `platform-signup`); a hidden `website` field catches bots.
 */
class SignUpController extends Controller
{
    /** Marks a real store for the `platform-signup` limiter: a rejected form is not counted. */
    public const CREATED_HEADER = 'X-Store-Created';

    public function create(): Response
    {
        return Inertia::render('Platform/SignUp', [
            'baseDomain' => config('tenancy.base_domain'),
            'businessTypes' => self::businessTypes(),
            'fonts' => collect(FontCatalog::all())
                ->map(fn (array $font, string $key) => [
                    'value' => $key,
                    'label' => $font['name'],
                    'stack' => $font['stack'],
                    'url' => FontCatalog::googleFontsUrl([$key]),
                ])
                ->values(),
            'colors' => array_values(config('store.color_presets')),
            'defaults' => ['color' => config('store.default_color'), 'font' => config('store.default_font')],
            'storeUrl' => url('/'.app()->getLocale().'/register-store'),
            'slugUrl' => url('/'.app()->getLocale().'/register-store/slug'),
        ]);
    }

    /**
     * The live check while typing the address.
     */
    public function slug(Request $request): JsonResponse
    {
        $slug = SlugAvailability::normalize((string) $request->query('slug', ''));
        $reason = SlugAvailability::check($slug);

        return response()->json([
            'slug' => $slug,
            'available' => $reason === null,
            'message' => $reason === null ? null : SlugAvailability::message($reason),
        ]);
    }

    public function store(Request $request, StoreProvisioner $provisioner): HttpResponse
    {
        $request->merge(['slug' => SlugAvailability::normalize((string) $request->input('slug'))]);

        $data = $request->validate([
            'storeName' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                if ($reason = SlugAvailability::check((string) $value)) {
                    $fail(SlugAvailability::message($reason));
                }
            }],
            'businessType' => ['nullable', Rule::in(array_column(self::businessTypes(), 'value'))],
            'ownerName' => ['required', 'string', 'max:120'],
            'ownerEmail' => ['required', 'email', 'max:255'],
            'ownerPhone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'color' => ['nullable', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                if (! BrandColor::isValid((string) $value)) {
                    $fail(__('Choose a color like #1F6F50.'));
                }
            }],
            'font' => ['nullable', Rule::in(array_keys(FontCatalog::all()))],
            'logo' => ['nullable', 'file', 'max:'.StoreAppearanceService::MAX_IMAGE_KB, 'mimes:png,jpg,jpeg,webp,gif'],
            'terms' => ['accepted'],
        ]);

        if (filled($request->input('website'))) {
            return back(); // a bot: say nothing, create nothing
        }

        try {
            $tenant = $provisioner->provision(new NewStoreData(
                slug: $data['slug'],
                nameAr: trim($data['storeName']),
                nameEn: null,
                businessType: $data['businessType'] ?? null,
                ownerName: trim($data['ownerName']),
                ownerEmail: $data['ownerEmail'],
                ownerPhone: filled($data['ownerPhone'] ?? null) ? trim($data['ownerPhone']) : null,
                password: $data['password'],
                contactEmail: null,
                primaryColor: $data['color'] ?? null,
                font: $data['font'] ?? null,
                logo: $request->file('logo'),
            ));
        } catch (StoreProvisioningFailed $e) {
            report($e);

            throw ValidationException::withMessages(['provisioning' => $e->getMessage()]);
        }

        $response = Inertia::location(StoreUrl::impersonation($tenant, $tenant->owner_user_id, app()->getLocale()));
        $response->headers->set(self::CREATED_HEADER, '1');

        return $response;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function businessTypes(): array
    {
        return [
            ['value' => 'fashion', 'label' => __('Clothes, shoes and accessories')],
            ['value' => 'beauty', 'label' => __('Beauty and personal care')],
            ['value' => 'electronics', 'label' => __('Electronics and phones')],
            ['value' => 'grocery', 'label' => __('Groceries and food')],
            ['value' => 'restaurant', 'label' => __('Restaurant or café')],
            ['value' => 'home', 'label' => __('Home, gifts and decor')],
            ['value' => 'other', 'label' => __('Something else')],
        ];
    }
}
