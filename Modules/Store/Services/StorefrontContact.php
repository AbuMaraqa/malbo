<?php

namespace Modules\Store\Services;

use Illuminate\Database\Eloquent\Collection;
use Modules\Branch\Repositories\Contracts\BranchRepositoryInterface;
use Modules\Store\Settings\StoreContactSettings;
use Modules\Store\Support\Storefront;

/**
 * How buyers reach the store, for the footer and the contact page: only what the owner set.
 */
class StorefrontContact
{
    private const SOCIALS = ['instagram_url' => 'Instagram', 'facebook_url' => 'Facebook', 'tiktok_url' => 'TikTok', 'x_url' => 'X'];

    private ?Collection $branches = null;

    public function __construct(private BranchRepositoryInterface $branchRepository) {}

    /**
     * @return array{phone?: string, whatsapp?: string, email?: string, address?: string}
     */
    public function channels(): array
    {
        $contact = app(StoreContactSettings::class);

        return array_filter([
            'phone' => $contact->phone,
            'whatsapp' => $contact->whatsapp,
            'email' => $contact->email,
            'address' => Storefront::t($contact->address),
        ]);
    }

    /**
     * @return array<string, string> label => url
     */
    public function socials(): array
    {
        $contact = app(StoreContactSettings::class);

        return collect(self::SOCIALS)
            ->mapWithKeys(fn (string $label, string $field) => [$label => $contact->{$field}])
            ->filter()
            ->all();
    }

    public function branches(): Collection
    {
        return $this->branches ??= $this->branchRepository->openForStorefront();
    }

    /**
     * "09:00–23:00" from a branch's opening and closing times, or null.
     */
    public static function hours(?string $opening, ?string $closing): ?string
    {
        return $opening && $closing ? substr($opening, 0, 5).'–'.substr($closing, 0, 5) : null;
    }
}
