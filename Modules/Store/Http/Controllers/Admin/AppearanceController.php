<?php

namespace Modules\Store\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Store\Http\Requests\Admin\AppearanceRequest;
use Modules\Store\Services\StoreAppearanceService;
use Modules\Store\Settings\StoreAppearanceSettings;
use Modules\Store\Settings\StoreContactSettings;
use Modules\Store\Support\Storefront;
use Modules\Store\Theme\FontCatalog;

/**
 * The store owner's look-and-feel page: brand color and font, home texts and images, the story and
 * values, the members section and contact links. Saving goes through StoreAppearanceService, which
 * clears the cached theme.
 */
class AppearanceController extends Controller
{
    /** upload field => settings property */
    private const IMAGE_PATHS = [
        'logo' => 'logo_path',
        'favicon' => 'favicon_path',
        'hero_image' => 'hero_image_path',
        'story_image' => 'story_image_path',
    ];

    public function __construct(private readonly StoreAppearanceService $appearanceService) {}

    public function index(): Response
    {
        $appearance = app(StoreAppearanceSettings::class);
        $contact = app(StoreContactSettings::class);

        $settings = [
            'primary_color' => $appearance->primary_color,
            'font' => $appearance->font,
            'values' => array_values($appearance->values),
            'membership_enabled' => $appearance->membership_enabled,
            'address' => $contact->address,
        ];

        foreach ([...AppearanceRequest::LINES, ...AppearanceRequest::PARAGRAPHS] as $field) {
            $settings[$field] = $appearance->{$field};
        }

        foreach (AppearanceRequest::CONTACT as $field) {
            $settings[$field] = $contact->{$field};
        }

        return Inertia::render('Store/Appearance/Index', [
            'settings' => $settings,
            'images' => array_map(fn (string $property) => Storefront::image($appearance->{$property}), self::IMAGE_PATHS),
            'presets' => array_values(config('store.color_presets')),
            'fonts' => collect(FontCatalog::all())->map(fn (array $font, string $key) => ['value' => $key, 'label' => $font['name']])->values(),
            'maxValues' => StoreAppearanceService::MAX_VALUES,
            'storeUrl' => route('home'),
        ]);
    }

    public function update(AppearanceRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $appearance = [
            'primary_color' => $data['primary_color'],
            'font' => $data['font'],
            // A form with files sends no key for an empty list: no values left means none.
            'values' => array_values($data['values'] ?? []),
            'membership_enabled' => (bool) ($data['membership_enabled'] ?? false),
        ];

        foreach ([...AppearanceRequest::LINES, ...AppearanceRequest::PARAGRAPHS] as $field) {
            $appearance[$field] = $data[$field] ?? [];
        }

        $contact = ['address' => $data['address'] ?? []];

        foreach (AppearanceRequest::CONTACT as $field) {
            $contact[$field] = $data[$field] ?? null;
        }

        // A new file replaces the image, a removed one is cleared (false), otherwise no change (null).
        $uploads = [];

        foreach (AppearanceRequest::IMAGES as $image) {
            $uploads[$image] = $request->file($image) ?? (in_array($image, $data['removed_images'] ?? [], true) ? false : null);
        }

        $this->appearanceService->save($appearance, $contact, $uploads);

        return to_route('appearance.index')->with('success', __('Saved Successfully'));
    }
}
