<?php

namespace Modules\Store\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Modules\Core\Traits\ApiResponse;
use Modules\Store\Services\StorefrontHome;

class HomeController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Get(
     *     path="/api/v1/store/home",
     *     summary="The store's home page texts",
     *     description="Hero, promises, story, values and the members circle in the requested language, as on the storefront's home page. A section the store did not fill is null (values: empty). Pages are given by slug (GET /api/v1/store/pages/{slug}). Products and categories come from /api/v1/items. Public.",
     *     tags={"Store"},
     *     @OA\Parameter(name="Accept-Language", in="header", required=false, description="Language header (ar or en)", @OA\Schema(type="string", default="ar", example="en")),
     *     @OA\Response(
     *         response=200,
     *         description="Home retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Home retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="hero", type="object",
     *                     @OA\Property(property="eyebrow", type="string", nullable=true),
     *                     @OA\Property(property="title", type="string", description="The store's name when not set"),
     *                     @OA\Property(property="subtitle", type="string", nullable=true),
     *                     @OA\Property(property="image", type="string", nullable=true)
     *                 ),
     *                 @OA\Property(property="promises", type="array", @OA\Items(type="object",
     *                     @OA\Property(property="key", type="string", enum={"shipping", "returns", "whatsapp", "privacy"}),
     *                     @OA\Property(property="title", type="string"),
     *                     @OA\Property(property="text", type="string"),
     *                     @OA\Property(property="page", type="string", nullable=true, description="Slug of the page behind it")
     *                 )),
     *                 @OA\Property(property="story", type="object", nullable=true,
     *                     @OA\Property(property="title", type="string", nullable=true),
     *                     @OA\Property(property="text", type="string"),
     *                     @OA\Property(property="image", type="string", nullable=true),
     *                     @OA\Property(property="about", type="string", nullable=true, description="Slug of the About page")
     *                 ),
     *                 @OA\Property(property="values", type="array", @OA\Items(type="object",
     *                     @OA\Property(property="title", type="string"),
     *                     @OA\Property(property="text", type="string", nullable=true)
     *                 )),
     *                 @OA\Property(property="membership", type="object", nullable=true, description="null while the store does not take members",
     *                     @OA\Property(property="title", type="string"),
     *                     @OA\Property(property="text", type="string")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index(StorefrontHome $home)
    {
        return $this->successResponse([
            'hero' => $home->hero(),
            'promises' => $home->promises(),
            'story' => $home->story(),
            'values' => $home->values(),
            'membership' => $home->membership(),
        ], __('Home retrieved successfully'));
    }
}
