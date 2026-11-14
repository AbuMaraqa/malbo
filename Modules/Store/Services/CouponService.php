<?php

namespace Modules\Store\Services;

use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Modules\Items\Entities\Category;
use Modules\Items\Entities\Offer;
use Modules\Items\Entities\Product;
use Modules\Items\Repositories\Contracts\CategoryRepositoryInterface;
use Modules\Items\Repositories\Contracts\OfferRepositoryInterface;
use Modules\Items\Repositories\Contracts\OrderRepositoryInterface;
use Modules\Store\Support\Coupon;
use Modules\Store\Support\Phone;
use Modules\Store\Support\Price;

/**
 * Coupons are offers with a code (an offer without one is not a coupon). A coupon covers the lines
 * of its offer's target: a product (with its variants), or a category (with its child categories;
 * a variant counts by its parent's categories). The offer's branch is ignored online.
 *
 * At checkout it must be active and inside its dates (an end date at midnight runs through that
 * day), cover at least one line, the covered lines must reach its minimum, and it must have uses
 * left: every order that used it counts, except cancelled or rejected ones. For the per-buyer
 * limit a buyer is their phone, and when signed in also their account: an order counts as theirs
 * when it has their account or their phone.
 *
 * Lines are arrays with `total`, `sellable_id` (the product sold: a variant's parent) and
 * `category_ids` (that product's categories); a line with `available` false is left out.
 */
class CouponService
{
    public function __construct(
        private readonly OfferRepositoryInterface $offers,
        private readonly OrderRepositoryInterface $orders,
        private readonly CategoryRepositoryInterface $categories,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $lines
     * @param  bool  $lock  inside the checkout transaction: holds the offer row until the order is saved
     * @param  ?int  $userId  a signed-in buyer: their account's orders count against the per-buyer limit too
     *
     * @throws ValidationException on `coupon`, with the reason for the buyer
     */
    public function check(string $code, array $lines, ?string $phone = null, bool $lock = false, ?int $userId = null): Coupon
    {
        $offer = $this->offers->findCoupon($code, $lock);
        $now = Carbon::now();

        if ($offer === null || ! $offer->is_active || $offer->type === null) {
            self::refuse(__('This coupon code is not valid.'));
        }

        if ($offer->start_date && $now->lt($offer->start_date)) {
            self::refuse(__('This coupon is not active yet.'));
        }

        if ($offer->end_date && $now->gt(self::endOf($offer))) {
            self::refuse(__('This coupon has expired.'));
        }

        $matching = $this->matchingTotal($offer, $lines);

        if ($matching === null) {
            self::refuse(__('This code does not apply to the items in your cart.'));
        }

        $minimum = (float) $offer->min_purchase_amount;

        if ($minimum > 0 && $matching < $minimum) {
            self::refuse(__('This coupon needs an order of at least :amount.', ['amount' => (new Price($minimum))->format()]));
        }

        $uses = $this->orders->couponUses((string) $offer->code);

        if ($offer->usage_limit !== null && count($uses) >= (int) $offer->usage_limit) {
            self::refuse(__('This coupon has been used up.'));
        }

        $hasPhone = $phone !== null && Phone::normalize($phone) !== '';

        if (($hasPhone || $userId !== null) && (int) $offer->usage_limit_per_user > 0) {
            // A use is this buyer's when it was their account's order, or placed with their phone.
            $mine = count(array_filter($uses, fn (array $use) => ($userId !== null && $use['user_id'] === $userId)
                || ($hasPhone && Phone::same($use['phone'], $phone))));

            if ($mine >= (int) $offer->usage_limit_per_user) {
                self::refuse(__('You have already used this coupon.'));
            }
        }

        return new Coupon($offer->id, (string) $offer->code, $offer->type, (float) $offer->value, $matching);
    }

    /**
     * The coupon an existing order used, applied to its lines as they are now (an order edited in
     * the admin): null when its offer is gone, no line is covered or the minimum is no longer met.
     * Dates and uses are not checked again: the order already used it.
     *
     * @param  list<array<string, mixed>>  $lines
     */
    public function forOrder(string $code, array $lines): ?Coupon
    {
        $offer = $this->offers->findCoupon($code);

        if ($offer === null || $offer->type === null) {
            return null;
        }

        $matching = $this->matchingTotal($offer, $lines);
        $minimum = (float) $offer->min_purchase_amount;

        if ($matching === null || ($minimum > 0 && $matching < $minimum)) {
            return null;
        }

        return new Coupon($offer->id, (string) $offer->code, $offer->type, (float) $offer->value, $matching);
    }

    /**
     * The total of the lines the offer covers, or null when it covers none.
     *
     * @param  list<array<string, mixed>>  $lines
     */
    private function matchingTotal(Offer $offer, array $lines): ?float
    {
        $target = (int) $offer->offerable_id;
        $matches = match (true) {
            self::is($offer, Product::class) => fn (array $line) => (int) ($line['sellable_id'] ?? 0) === $target,
            self::is($offer, Category::class) => (function () use ($target) {
                $tree = array_flip($this->categories->treeIds($target));

                return fn (array $line) => array_intersect_key(array_flip(array_map('intval', $line['category_ids'] ?? [])), $tree) !== [];
            })(),
            default => fn () => false,
        };

        $covered = array_filter($lines, fn (array $line) => ($line['available'] ?? true) && $matches($line));

        return $covered === [] ? null : round(array_sum(array_map(fn (array $line) => (float) $line['total'], $covered)), 2);
    }

    /**
     * @param  class-string  $class
     */
    private static function is(Offer $offer, string $class): bool
    {
        return in_array($offer->offerable_type, [$class, (new $class)->getMorphClass()], true);
    }

    private static function endOf(Offer $offer): Carbon
    {
        $end = Carbon::parse($offer->end_date);

        return $end->format('H:i:s') === '00:00:00' ? $end->endOfDay() : $end;
    }

    /**
     * @throws ValidationException
     */
    private static function refuse(string $message): never
    {
        throw ValidationException::withMessages(['coupon' => $message]);
    }
}
