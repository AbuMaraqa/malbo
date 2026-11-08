<?php

namespace Modules\Store\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Modules\Store\Http\Requests\MemberRequest;
use Modules\Store\Services\MemberService;
use Modules\Store\Services\StorefrontHome;
use Modules\Store\Support\StorefrontLayout;
use Symfony\Component\HttpFoundation\Response;

/**
 * The members form: on the home page and on its own page, for a link the store can share. Name,
 * phone and an optional email go to the store's members list, with the contact form's spam guards.
 */
class JoinMembersController extends Controller
{
    public function __construct(
        private readonly MemberService $memberService,
        private readonly StorefrontHome $home,
        private readonly StorefrontLayout $layout,
    ) {}

    public function show(Request $request): Response
    {
        $membership = $this->home->membership();

        if ($membership === null) {
            return app(NotFoundController::class)($request);
        }

        return Inertia::render('Store/Storefront/JoinMembers', [
            ...$this->layout->props(['title' => $membership['title'], 'description' => $membership['text']]),
            'membership' => $membership,
        ])->toResponse($request);
    }

    public function store(MemberRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if (! $this->memberService->allowFrom($request->ip())) {
            throw ValidationException::withMessages(['phone' => __('Please try again in a few minutes.')]);
        }

        // `website` is left empty by people; a bot fills it, and is welcomed like anyone else.
        $new = true;
        if (blank($request->input('website'))) {
            $new = $this->memberService->join($data['name'], $data['phone'], ($data['email'] ?? null) ?: null, $request->ip())['new'];
        }

        return back(fallback: route('home'))->with('success', $new ? __('Welcome to the circle.') : __('You are already one of our members.'));
    }
}
