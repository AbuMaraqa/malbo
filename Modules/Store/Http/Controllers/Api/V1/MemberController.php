<?php

namespace Modules\Store\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Modules\Core\Traits\ApiResponse;
use Modules\Store\Http\Requests\MemberRequest;
use Modules\Store\Services\MemberService;
use Modules\Store\Settings\StoreAppearanceSettings;

class MemberController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Post(
     *     path="/api/v1/store/members",
     *     summary="Join the store's members circle",
     *     description="Saved to the dashboard's members list, once per phone number (joining again updates the name and email). Only while the store takes members (GET /api/v1/store/home: membership is not null). At most 3 sign-ups per visitor every 10 minutes. Public.",
     *     tags={"Store"},
     *     @OA\Parameter(name="Accept-Language", in="header", required=false, description="Language of the messages (ar or en)", @OA\Schema(type="string", default="ar", example="en")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "phone"},
     *             @OA\Property(property="name", type="string", maxLength=120, example="سارة"),
     *             @OA\Property(property="phone", type="string", maxLength=40, example="0599 123 456"),
     *             @OA\Property(property="email", type="string", format="email", maxLength=160)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Joined, or already a member (data.new = false)", @OA\JsonContent(
     *         @OA\Property(property="status", type="boolean", example=true),
     *         @OA\Property(property="message", type="string", example="Welcome to the circle."),
     *         @OA\Property(property="data", type="object", @OA\Property(property="new", type="boolean", example=true))
     *     )),
     *     @OA\Response(response=403, description="The store does not take members"),
     *     @OA\Response(response=422, description="Validation Error: data holds the messages per field"),
     *     @OA\Response(response=429, description="Too many sign-ups: try again in a few minutes")
     * )
     */
    public function store(MemberRequest $request, MemberService $memberService)
    {
        if (! app(StoreAppearanceSettings::class)->membership_enabled) {
            return $this->errorResponse(__('This store is not taking members.'), 403);
        }

        if (! $memberService->allowFrom($request->ip())) {
            return $this->errorResponse(__('Please try again in a few minutes.'), 429);
        }

        $data = $request->validated();
        $new = $memberService->join($data['name'], $data['phone'], $data['email'] ?? null, $request->ip())['new'];

        return $this->successResponse(
            ['new' => $new],
            $new ? __('Welcome to the circle.') : __('You are already one of our members.'),
            201
        );
    }
}
