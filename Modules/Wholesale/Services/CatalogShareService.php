<?php

namespace Modules\Wholesale\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Items\Repositories\Contracts\CategoryRepositoryInterface;
use Modules\Wholesale\Entities\CatalogShare;
use Modules\Wholesale\Entities\Merchant;
use Modules\Wholesale\Repositories\Contracts\CatalogShareRepositoryInterface;

/**
 * A merchant's shared catalog links: made for a category (or all) in a language, with retail
 * prices (the default) or the merchant's own wholesale prices, valid
 * CatalogShare::DAYS days, revoked by the merchant at any time.
 */
class CatalogShareService
{
    public function __construct(
        private readonly CatalogShareRepositoryInterface $shares,
        private readonly CategoryRepositoryInterface $categories,
    ) {}

    /**
     * @throws ValidationException on `category` when it is not an active category
     */
    public function create(Merchant $merchant, ?string $category, string $locale, string $prices = CatalogPdfService::RETAIL): CatalogShare
    {
        $categoryId = null;

        if (filled($category)) {
            $categoryId = $this->categories->findActiveBySlug((string) $category)?->id
                ?? throw ValidationException::withMessages(['category' => __('Choose one of the categories.')]);
        }

        /** @var CatalogShare */
        return $this->shares->create([
            'merchant_id' => $merchant->id,
            'token' => Str::random(48),
            'category_id' => $categoryId,
            'locale' => $locale,
            'prices' => in_array($prices, CatalogPdfService::PRICES, true) ? $prices : CatalogPdfService::RETAIL,
            'expires_at' => Carbon::now()->addDays(CatalogShare::DAYS),
        ]);
    }

    public function revoke(CatalogShare $share): void
    {
        if ($share->revoked_at === null) {
            $this->shares->update($share, ['revoked_at' => Carbon::now()]);
            CatalogPdfService::forgetShare($share);
        }
    }
}
