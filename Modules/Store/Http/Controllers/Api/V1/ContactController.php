<?php

namespace Modules\Store\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Modules\Core\Traits\ApiResponse;
use Modules\Store\Services\ContactMessageService;
use Modules\Store\Services\StorefrontContact;
use Modules\Store\Settings\StoreContactSettings;
use Modules\Store\Support\Storefront;
use Modules\Store\Transformers\V1\StoreBranchResource;

class ContactController extends Controller
{
    use ApiResponse;

    private const SOCIALS = ['instagram', 'facebook', 'tiktok', 'x'];

    /**
     * @OA\Get(
     *     path="/api/v1/store/contact",
     *     summary="How to reach the store",
     *     description="Phone, WhatsApp (international number, digits only: open https://wa.me/{whatsapp}), email and address, social links, open branches with their hours, and the topics for the contact form. What the store did not set is null. Public.",
     *     tags={"Store"},
     *     @OA\Parameter(name="Accept-Language", in="header", required=false, description="Language header (ar or en)", @OA\Schema(type="string", default="ar", example="en")),
     *     @OA\Response(
     *         response=200,
     *         description="Contact details retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Contact details retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="phone", type="string", nullable=true, example="0599123456"),
     *                 @OA\Property(property="whatsapp", type="string", nullable=true, example="970599123456"),
     *                 @OA\Property(property="email", type="string", nullable=true),
     *                 @OA\Property(property="address", type="string", nullable=true),
     *                 @OA\Property(property="socials", type="array", @OA\Items(type="object",
     *                     @OA\Property(property="network", type="string", enum={"instagram", "facebook", "tiktok", "x"}),
     *                     @OA\Property(property="url", type="string")
     *                 )),
     *                 @OA\Property(property="branches", type="array", @OA\Items(type="object",
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="name", type="string"),
     *                     @OA\Property(property="address", type="string", nullable=true),
     *                     @OA\Property(property="phone", type="string", nullable=true),
     *                     @OA\Property(property="hours", type="string", nullable=true, example="09:00–23:00"),
     *                     @OA\Property(property="latitude", type="number", nullable=true),
     *                     @OA\Property(property="longitude", type="number", nullable=true)
     *                 )),
     *                 @OA\Property(property="topics", type="array", @OA\Items(type="object",
     *                     @OA\Property(property="key", type="string", example="delivery"),
     *                     @OA\Property(property="label", type="string", example="التوصيل")
     *                 ))
     *             )
     *         )
     *     )
     * )
     */
    public function index(StorefrontContact $contact)
    {
        $settings = app(StoreContactSettings::class);

        return $this->successResponse([
            'phone' => $settings->phone,
            'whatsapp' => $settings->whatsapp,
            'email' => $settings->email,
            'address' => Storefront::t($settings->address),
            'socials' => collect(self::SOCIALS)
                ->map(fn (string $network) => ['network' => $network, 'url' => $settings->{$network.'_url'}])
                ->filter(fn (array $social) => filled($social['url']))
                ->values(),
            'branches' => StoreBranchResource::collection($contact->branches()),
            'topics' => collect(ContactMessageService::topics())
                ->map(fn (string $label, string $key) => ['key' => $key, 'label' => $label])
                ->values(),
        ], __('Contact details retrieved successfully'));
    }
}
