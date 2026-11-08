<?php

namespace Modules\Store\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\Locales;
use Modules\Core\Settings\GeneralSettings;
use Modules\Core\Traits\ApiResponse;
use Modules\Store\Settings\StoreAppearanceSettings;
use Modules\Store\Support\Storefront;
use Modules\Store\Theme\ThemeService;

class AppearanceController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Get(
     *     path="/api/v1/store/appearance",
     *     summary="The store's look for the app",
     *     description="Name, logo, brand color with its 50–900 shades and the text color on it, font and announcement, as set on the dashboard's appearance page. Public. `version` changes whenever any of it does: cache the theme and refetch when the app comes back to the foreground.",
     *     tags={"Store"},
     *     @OA\Parameter(name="Accept-Language", in="header", required=false, description="Language header (ar or en)", @OA\Schema(type="string", default="ar", example="en")),
     *     @OA\Response(
     *         response=200,
     *         description="Appearance retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Appearance retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="name", type="string", example="متجر الأناقة"),
     *                 @OA\Property(property="logo", type="string", nullable=true, example="https://shop.example.com/storage/store/logo.png"),
     *                 @OA\Property(property="favicon", type="string", nullable=true),
     *                 @OA\Property(property="color", type="string", example="#1F6F50"),
     *                 @OA\Property(property="on_color", type="string", description="Text color that reads on `color`", example="#FFFFFF"),
     *                 @OA\Property(property="shades", type="object", description="50 (lightest) … 900 (darkest); 500 is `color`", example={"50": "#EEF9F4", "500": "#1F6F50", "900": "#07160F"}),
     *                 @OA\Property(property="font", type="object", @OA\Property(property="key", type="string", example="tajawal"), @OA\Property(property="name", type="string", description="Google Fonts family name", example="Tajawal")),
     *                 @OA\Property(property="announcement", type="string", nullable=true),
     *                 @OA\Property(property="locale", type="string", example="ar"),
     *                 @OA\Property(property="rtl", type="boolean", example=true),
     *                 @OA\Property(property="locales", type="array", @OA\Items(type="string"), example={"ar", "en"}),
     *                 @OA\Property(property="version", type="string", example="3f9a1c0b7d2e")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Unknown store")
     * )
     */
    public function index(ThemeService $theme)
    {
        $appearance = app(StoreAppearanceSettings::class);
        $tokens = $theme->tokens();

        $data = [
            'name' => (string) app(GeneralSettings::class)->site_name,
            'logo' => Storefront::image($appearance->logo_path),
            'favicon' => Storefront::image($appearance->favicon_path),
            'color' => $tokens['color'],
            'on_color' => $tokens['on_color'],
            'shades' => $tokens['shades'],
            'font' => ['key' => $tokens['font']['key'], 'name' => $tokens['font']['name']],
            'announcement' => Storefront::t($appearance->announcement),
            'locale' => app()->getLocale(),
            'rtl' => Storefront::rtl(),
            'locales' => \App\Support\Locales::keys(),
        ];

        return $this->successResponse(
            // Changes whenever any of the above does: the app compares it with the theme it cached.
            [...$data, 'version' => substr(md5(json_encode($data)), 0, 12)],
            __('Appearance retrieved successfully')
        );
    }
}
