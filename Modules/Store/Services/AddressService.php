<?php

namespace Modules\Store\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Core\Entities\User;
use Modules\Store\Entities\Address;
use Modules\Store\Repositories\Contracts\AddressRepositoryInterface;
use Modules\Store\Support\Phone;

/**
 * Writes a buyer's saved addresses. At most one is the default (a partial unique index backs it):
 * the first address is the default, a new default takes the place of the old one, and deleting
 * the default makes the newest address left the default. A buyer keeps at most LIMIT addresses.
 * The user's addresses are locked while the default moves, so two tabs cannot leave two defaults.
 */
class AddressService
{
    public const LIMIT = 20;

    public function __construct(private readonly AddressRepositoryInterface $addresses) {}

    /**
     * @param  array<string, mixed>  $data  AddressRequest's fields
     *
     * @throws ValidationException on `address` once the limit is reached
     */
    public function create(User $user, array $data): Address
    {
        return DB::transaction(function () use ($user, $data): Address {
            $mine = $this->addresses->lockForUser((int) $user->id);

            if ($mine->count() >= self::LIMIT) {
                throw ValidationException::withMessages(['address' => __('You can keep up to :count addresses. Delete one first.', ['count' => self::LIMIT])]);
            }

            $default = $mine->isEmpty() || (bool) ($data['is_default'] ?? false);

            if ($default) {
                $this->clearDefault((int) $user->id);
            }

            /** @var Address */
            return $this->addresses->create([...self::fields($data), 'user_id' => $user->id, 'is_default' => $default]);
        });
    }

    /**
     * Whether there is room for one more address.
     */
    public function hasRoom(User $user): bool
    {
        return $this->addresses->countForUser((int) $user->id) < self::LIMIT;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Address $address, array $data): Address
    {
        return DB::transaction(function () use ($address, $data): Address {
            $this->addresses->lockForUser((int) $address->user_id);

            // Unticking the default keeps it: there is always one while there are addresses.
            $default = $address->is_default || (bool) ($data['is_default'] ?? false);

            if ($default && ! $address->is_default) {
                $this->clearDefault((int) $address->user_id);
            }

            $address->update([...self::fields($data), 'is_default' => $default]);

            return $address;
        });
    }

    public function makeDefault(Address $address): void
    {
        DB::transaction(function () use ($address): void {
            $this->addresses->lockForUser((int) $address->user_id);
            $this->clearDefault((int) $address->user_id);
            $address->update(['is_default' => true]);
        });
    }

    public function delete(Address $address): void
    {
        DB::transaction(function () use ($address): void {
            $mine = $this->addresses->lockForUser((int) $address->user_id);
            $wasDefault = $mine->firstWhere('id', $address->id)?->is_default ?? $address->is_default;

            $this->addresses->delete($address);

            $next = $mine->reject(fn (Address $other) => $other->id === $address->id)->sortByDesc('id')->first();

            if ($wasDefault && $next !== null) {
                $next->update(['is_default' => true]);
            }
        });
    }

    private function clearDefault(int $userId): void
    {
        $this->addresses->query()->where('user_id', $userId)->where('is_default', true)->update(['is_default' => false]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function fields(array $data): array
    {
        return [
            'label' => filled($data['label'] ?? null) ? trim((string) $data['label']) : null,
            'recipient_name' => trim((string) $data['recipient_name']),
            'phone' => Phone::canonical((string) $data['phone']),
            'city_id' => (int) $data['city_id'],
            'district_id' => filled($data['district_id'] ?? null) ? (int) $data['district_id'] : null,
            'address_line' => trim((string) $data['address_line']),
        ];
    }
}
