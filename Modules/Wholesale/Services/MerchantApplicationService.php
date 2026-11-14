<?php

namespace Modules\Wholesale\Services;

use Illuminate\Auth\Events\Registered;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;
use Modules\Core\Repositories\Contracts\UserRepositoryInterface;
use Modules\Store\Support\Phone;
use Modules\Wholesale\Entities\Merchant;
use Modules\Wholesale\Enums\MerchantStatus;
use Modules\Wholesale\Repositories\Contracts\MerchantRepositoryInterface;
use Throwable;

/**
 * A shop asks to buy wholesale (the public join form): one transaction makes the account (the
 * Merchant role only, so it never reaches the admin), its pending profile and the first line of its
 * history. The commercial register's scan goes to the private disk; only staff download it, through
 * a signed link. The new account is signed in and sees "pending review" until staff approve it.
 */
class MerchantApplicationService
{
    /** The private disk, under storage/app/private: never served without a signed link. */
    public const DISK = 'local';

    /** Applications that went through, an hour, from one address. */
    public const APPLICATIONS_PER_HOUR = 3;

    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly MerchantRepositoryInterface $merchants,
    ) {}

    /**
     * @param  array{shop_name: string, owner_name: string, city_id: int|string, phone: string, email: string, commercial_register: string, shop_address?: ?string, password: string}  $data
     *
     * @throws ValidationException on `email` when two applications with one email race
     */
    public function apply(array $data, ?UploadedFile $document, Request $request): Merchant
    {
        // A few new shops an hour from one address is plenty, and stops a script from filling the
        // merchants list. Only applications that went through count (mistakes have the route's limit).
        $limit = 'wholesale-apply-success:'.$request->ip();

        if (RateLimiter::tooManyAttempts($limit, self::APPLICATIONS_PER_HOUR)) {
            throw ValidationException::withMessages(['email' => __('Too many attempts. Try again later.')]);
        }

        $path = $document ? $this->store($document) : null;

        try {
            [$user, $merchant] = DB::transaction(function () use ($data, $document, $path): array {
                $email = mb_strtolower(trim($data['email']));
                $phone = Phone::canonical($data['phone']);

                /** @var User $user */
                $user = $this->users->create([
                    'name' => trim($data['owner_name']),
                    'email' => $email,
                    'phone' => $phone,
                    'password' => $data['password'], // the model's `hashed` cast hashes it
                ]);
                $user->assignRole(UserRole::MERCHANT->value);

                /** @var Merchant $merchant */
                $merchant = $this->merchants->create([
                    'user_id' => $user->id,
                    'shop_name' => trim($data['shop_name']),
                    'owner_name' => trim($data['owner_name']),
                    'city_id' => (int) $data['city_id'],
                    'phone' => $phone,
                    'email' => $email,
                    'commercial_register' => trim($data['commercial_register']),
                    'shop_address' => filled($data['shop_address'] ?? null) ? trim((string) $data['shop_address']) : null,
                    'register_document_path' => $path,
                    'register_document_name' => $document ? mb_substr($document->getClientOriginalName(), 0, 255) : null,
                    'status' => MerchantStatus::PENDING,
                ]);

                $merchant->statusChanges()->create([
                    'from_status' => null,
                    'to_status' => MerchantStatus::PENDING,
                    'note' => null,
                    'created_by' => $user->id,
                ]);

                return [$user, $merchant];
            });
        } catch (UniqueConstraintViolationException) {
            $this->forget($path);

            throw ValidationException::withMessages(['email' => __('An account with this email already exists. Sign in instead.')]);
        } catch (Throwable $e) {
            $this->forget($path);

            throw $e;
        }

        RateLimiter::hit($limit, 3600);

        event(new Registered($user));

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return $merchant;
    }

    private function store(UploadedFile $document): string
    {
        $extension = strtolower($document->guessExtension() ?: $document->getClientOriginalExtension() ?: 'bin');

        return Storage::disk(self::DISK)->putFileAs('wholesale/registers', $document, Str::uuid().'.'.$extension);
    }

    private function forget(?string $path): void
    {
        if ($path !== null) {
            Storage::disk(self::DISK)->delete($path);
        }
    }
}
