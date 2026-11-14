<?php

namespace Modules\Store\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Store\Http\Requests\AccountPasswordRequest;
use Modules\Store\Http\Requests\ProfileRequest;
use Modules\Store\Services\CustomerProfileService;
use Modules\Store\Support\StorefrontLayout;

/**
 * The account's first page: the buyer's details and their password. Buyers and staff alike.
 */
class AccountController extends Controller
{
    public function __construct(
        private readonly CustomerProfileService $profileService,
        private readonly StorefrontLayout $layout,
    ) {}

    public function show(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Store/Storefront/Account/Profile', [
            ...$this->layout->props(['title' => __('My account'), 'noindex' => true]),
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
            ],
        ]);
    }

    public function update(ProfileRequest $request): RedirectResponse
    {
        $this->profileService->update($request->user(), $request->validated());

        return back()->with('success', __('Your details were saved.'));
    }

    public function password(AccountPasswordRequest $request): RedirectResponse
    {
        $this->profileService->changePassword($request->user(), $request->validated('password'), $request);

        return back()->with('success', __('Your password was changed.'));
    }
}
