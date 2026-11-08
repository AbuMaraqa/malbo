<?php

namespace Modules\Store\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Store\Http\Requests\ContactMessageRequest;
use Modules\Store\Repositories\Contracts\PageRepositoryInterface;
use Modules\Store\Services\ContactMessageService;
use Modules\Store\Services\StorefrontContact;
use Modules\Store\Support\StorefrontLayout;

/**
 * How to reach the store: a message form that lands in the admin inbox, and WhatsApp, phone,
 * email, address, branches with hours and socials when the store set them.
 */
class ContactController extends Controller
{
    public function __construct(
        private readonly ContactMessageService $contactMessageService,
        private readonly StorefrontContact $contact,
        private readonly PageRepositoryInterface $pages,
        private readonly StorefrontLayout $layout,
    ) {}

    public function show(): Response
    {
        $faq = $this->pages->publishedKeys()['faq'] ?? null;

        return Inertia::render('Store/Storefront/Contact', [
            ...$this->layout->props([
                'title' => __('Contact us'),
                'description' => __('We are happy to help with orders, sizes, delivery or anything else.'),
            ]),
            'channels' => $this->contact->channels() ?: null,
            'socials' => $this->layout->socials(),
            'branches' => $this->layout->branches(),
            'faqUrl' => $faq ? route('store.page', ['slug' => $faq]) : null,
            'topics' => collect(ContactMessageService::topics())
                ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
                ->values()
                ->all(),
        ]);
    }

    public function send(ContactMessageRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if (! $this->contactMessageService->allowFrom($request->ip())) {
            throw ValidationException::withMessages(['message' => __('You have sent several messages. Please try again in a few minutes.')]);
        }

        // `website` is left empty by people; a bot fills it, and is thanked like anyone else.
        if (blank($request->input('website'))) {
            $topic = $data['topic'] ?? null;

            $this->contactMessageService->receive([
                ...$data,
                'subject' => $topic ? ContactMessageService::topics()[$topic] : null,
            ], $request->ip());
        }

        return to_route('store.contact')->with('success', __('Thank you, your message has been sent.'));
    }
}
