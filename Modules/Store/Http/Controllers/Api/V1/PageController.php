<?php

namespace Modules\Store\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Modules\Core\Traits\ApiResponse;
use Modules\Store\Repositories\Contracts\PageRepositoryInterface;
use Modules\Store\Transformers\V1\PageDetailResource;
use Modules\Store\Transformers\V1\PageResource;

class PageController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Get(
     *     path="/api/v1/store/pages",
     *     summary="The store's published pages",
     *     description="About, FAQ, the policies and the pages the owner added, in sort order. `key` is set on the system pages (about, shipping, returns, privacy, terms, faq). Public.",
     *     tags={"Store"},
     *     @OA\Parameter(name="Accept-Language", in="header", required=false, description="Language header (ar or en)", @OA\Schema(type="string", default="ar", example="en")),
     *     @OA\Response(
     *         response=200,
     *         description="Pages retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Pages retrieved successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="key", type="string", nullable=true, example="shipping"),
     *                 @OA\Property(property="slug", type="string", example="shipping"),
     *                 @OA\Property(property="title", type="string", example="سياسة الشحن"),
     *                 @OA\Property(property="meta_description", type="string", nullable=true),
     *                 @OA\Property(property="group", type="string", enum={"policies", "about"}),
     *                 @OA\Property(property="show_in_footer", type="boolean"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             ))
     *         )
     *     )
     * )
     */
    public function index(PageRepositoryInterface $pages)
    {
        return $this->successResponse(
            PageResource::collection($pages->published()),
            __('Pages retrieved successfully')
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/store/pages/{slug}",
     *     summary="One store page",
     *     description="The page's fields from the list, plus `body` as HTML with the store's details filled in. For the FAQ, `faq` also holds the intro and the questions with their answers (HTML). Public.",
     *     tags={"Store"},
     *     @OA\Parameter(name="Accept-Language", in="header", required=false, description="Language header (ar or en)", @OA\Schema(type="string", default="ar", example="en")),
     *     @OA\Parameter(name="slug", in="path", required=true, @OA\Schema(type="string", example="shipping")),
     *     @OA\Response(
     *         response=200,
     *         description="Page retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Page retrieved successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="slug", type="string", example="faq"),
     *                 @OA\Property(property="title", type="string"),
     *                 @OA\Property(property="body", type="string", nullable=true, description="HTML"),
     *                 @OA\Property(property="faq", type="object", nullable=true,
     *                     @OA\Property(property="intro", type="string"),
     *                     @OA\Property(property="items", type="array", @OA\Items(type="object",
     *                         @OA\Property(property="question", type="string"),
     *                         @OA\Property(property="answer", type="string", description="HTML")
     *                     ))
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="No published page with this slug")
     * )
     */
    public function show(string $slug, PageRepositoryInterface $pages)
    {
        $page = $pages->findPublishedBySlug($slug) ?? abort(404);

        return $this->successResponse(new PageDetailResource($page), __('Page retrieved successfully'));
    }
}
