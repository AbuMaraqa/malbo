<?php

namespace Modules\Core\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Modules\Core\Entities\User;
use Modules\Core\Http\Requests\Admin\ProfilePasswordRequest;
use Modules\Core\Http\Requests\Admin\ProfileRequest;
use Modules\Core\Services\ProfileService;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ProfileController extends Controller
{
    public function __construct(private readonly ProfileService $profileService) {}

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user()->load('media');

        return Inertia::render('Core/Profile/Index', [
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'avatar_url' => $user->avatar_url,
                'cover_url' => $user->getFirstMediaUrl('cover_photo') ?: null,
                'avatar' => $this->image($user->getFirstMedia('avatar')),
                'cover_photo' => $this->image($user->getFirstMedia('cover_photo')),
                'roles' => $user->getRoleNames()->map(fn (string $role) => __($role))->values(),
            ],
            'twoFactor' => Features::canManageTwoFactorAuthentication() ? $this->twoFactor($request, $user) : null,
        ]);
    }

    public function update(ProfileRequest $request): RedirectResponse
    {
        $this->profileService->update($request->user(), $request->safe()->except(['avatar', 'cover_photo']), [
            'avatar' => $request->file('avatar'),
            'cover_photo' => $request->file('cover_photo'),
        ]);

        return to_route('profile.index')->with('success', __('Saved Successfully'));
    }

    public function password(ProfilePasswordRequest $request): RedirectResponse
    {
        $this->profileService->changePassword($request->user(), $request->validated('password'));

        return to_route('profile.index')->with('success', __('Password changed successfully.'));
    }

    /**
     * The setup QR code is the secret itself: only sent after the password was confirmed. Recovery
     * codes are only sent right after they were generated.
     *
     * @return array<string, mixed>
     */
    private function twoFactor(Request $request, User $user): array
    {
        $confirmed = $user->hasEnabledTwoFactorAuthentication();
        $inSetup = ! $confirmed && $user->two_factor_secret !== null && $this->passwordRecentlyConfirmed($request);

        return [
            'enabled' => $confirmed,
            'qrCodeSvg' => $inSetup ? $user->twoFactorQrCodeSvg() : null,
            'secretKey' => $inSetup ? Fortify::currentEncrypter()->decrypt($user->two_factor_secret) : null,
            'recoveryCodes' => $confirmed && $request->session()->get('two_factor_show_recovery_codes') ? $user->recoveryCodes() : null,
        ];
    }

    private function passwordRecentlyConfirmed(Request $request): bool
    {
        return time() - (int) $request->session()->get('auth.password_confirmed_at', 0) < (int) config('auth.password_timeout', 10800);
    }

    /**
     * @return list<array{id: int, url: string}>
     */
    private function image(?Media $media): array
    {
        return $media ? [['id' => $media->id, 'url' => $media->getUrl()]] : [];
    }
}
