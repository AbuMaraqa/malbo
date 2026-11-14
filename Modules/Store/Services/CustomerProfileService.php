<?php

namespace Modules\Store\Services;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Core\Entities\User;
use Modules\Store\Support\Phone;

/**
 * A buyer's own profile in the storefront account: name, email and phone (a new email needs the
 * current password: ProfileRequest), and a new password once the current one is confirmed
 * (AccountPasswordRequest).
 */
class CustomerProfileService
{
    /**
     * @param  array{name: string, email: string, phone: string}  $data
     *
     * @throws ValidationException on `email` when another account took it meanwhile
     */
    public function update(User $user, array $data): User
    {
        try {
            $user->forceFill([
                'name' => trim($data['name']),
                'email' => mb_strtolower(trim($data['email'])),
                'phone' => Phone::canonical($data['phone']),
            ])->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => __('Another account already uses this email.')]);
        }

        return $user;
    }

    /**
     * The new password, then every other signed-in device of the account is signed out: a new
     * remember token ends "remember me" elsewhere, and logoutOtherDevices ends their sessions (the
     * account pages check it: `auth.session`). This device keeps going on a fresh session id.
     */
    public function changePassword(User $user, string $password, Request $request): void
    {
        // The model's `hashed` cast hashes it.
        $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();

        Auth::guard('web')->logoutOtherDevices($password);
        $request->session()->regenerate();
    }
}
