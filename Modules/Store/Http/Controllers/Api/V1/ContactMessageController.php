<?php

namespace Modules\Store\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Modules\Core\Traits\ApiResponse;
use Modules\Store\Http\Requests\ContactMessageRequest;
use Modules\Store\Services\ContactMessageService;

class ContactMessageController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Post(
     *     path="/api/v1/store/contact-messages",
     *     summary="Send the store a message",
     *     description="Lands in the dashboard's messages inbox, like the storefront's contact form. A phone or an email is required. At most 3 messages per visitor every 10 minutes (shared with the website form). Public.",
     *     tags={"Store"},
     *     @OA\Parameter(name="Accept-Language", in="header", required=false, description="Language of the messages (ar or en)", @OA\Schema(type="string", default="ar", example="en")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "message"},
     *             @OA\Property(property="name", type="string", maxLength=120, example="سارة"),
     *             @OA\Property(property="phone", type="string", maxLength=40, example="0599 123 456"),
     *             @OA\Property(property="email", type="string", format="email", maxLength=160),
     *             @OA\Property(property="topic", type="string", enum={"product", "order", "delivery", "returns", "other"}),
     *             @OA\Property(property="message", type="string", minLength=10, maxLength=2000, example="متى يصل طلبي إلى نابلس؟")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Message sent", @OA\JsonContent(
     *         @OA\Property(property="status", type="boolean", example=true),
     *         @OA\Property(property="message", type="string", example="Thank you, your message has been sent."),
     *         @OA\Property(property="data", type="object", @OA\Property(property="id", type="integer", example=12))
     *     )),
     *     @OA\Response(response=422, description="Validation Error: data holds the messages per field"),
     *     @OA\Response(response=429, description="Too many messages: try again in a few minutes")
     * )
     */
    public function store(ContactMessageRequest $request, ContactMessageService $contactMessageService)
    {
        if (! $contactMessageService->allowFrom($request->ip())) {
            return $this->errorResponse(__('You have sent several messages. Please try again in a few minutes.'), 429);
        }

        $data = $request->validated();
        $message = $contactMessageService->receive([
            ...$data,
            'subject' => filled($data['topic'] ?? null) ? ContactMessageService::topics()[$data['topic']] : null,
        ], $request->ip());

        return $this->successResponse(['id' => $message->id], __('Thank you, your message has been sent.'), 201);
    }
}
