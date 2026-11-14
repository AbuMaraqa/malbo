<?php

namespace Modules\Store\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Modules\Store\Entities\Cart;
use Modules\Store\Entities\CartItem;
use Modules\Store\Entities\CartReminder;
use Modules\Items\Repositories\Contracts\ProductRepositoryInterface;
use Modules\Store\Repositories\Contracts\CartRepositoryInterface;
use Modules\Store\Support\CartCookie;
use Modules\Store\Support\Phone;

/**
 * Abandoned carts, the buyer's side: the contact they agreed (at checkout, "Remind me about my
 * cart") to be reminded at, and the reminder's link that brings the cart back to a browser.
 */
class AbandonedCartService
{
    public const RESTORED = 'restored';

    public const ORDERED = 'ordered';

    public const SIGN_IN = 'sign_in';

    public const FORBIDDEN = 'forbidden';

    public const MISSING = 'missing';

    public function __construct(
        private readonly CartRepositoryInterface $carts,
        private readonly ProductRepositoryInterface $products,
        private readonly CartService $cart,
        private readonly PricingService $pricing,
    ) {}

    /**
     * With $remind, keeps the contact (email lowercased, phone as an order keeps it), the moment the
     * buyer agreed and the cart's language. Without it, forgets them all: the buyer took it back.
     */
    public function saveContact(Cart $cart, bool $remind, ?string $email, ?string $phone, string $locale): void
    {
        $cart->update($remind ? [
            'contact_email' => filled($email) ? mb_strtolower(trim((string) $email)) : null,
            'contact_phone' => filled($phone) ? Phone::canonical((string) $phone) : null,
            'contact_consent_at' => $cart->contact_consent_at ?? Carbon::now(),
            'locale' => $locale,
        ] : [
            'contact_email' => null,
            'contact_phone' => null,
            'contact_consent_at' => null,
        ]);
    }

    /**
     * Where a reminder goes: the contact typed at checkout, else the account's. Nothing without the
     * buyer's agreement.
     *
     * @return array{email: ?string, phone: ?string}
     */
    public function contact(Cart $cart): array
    {
        if ($cart->contact_consent_at === null) {
            return ['email' => null, 'phone' => null];
        }

        $email = $cart->contact_email ?: $cart->user?->email;
        $phone = $cart->contact_phone ?: $cart->user?->phone;

        return [
            'email' => filled($email) ? mb_strtolower(trim((string) $email)) : null,
            'phone' => filled($phone) && Phone::plausible((string) $phone) ? Phone::canonical((string) $phone) : null,
        ];
    }

    /**
     * What these carts are worth at today's prices: the lines that can still be bought, as the cart
     * page counts them. One read of the products for all the carts.
     *
     * @param  Collection<int, Cart>  $carts  with their items
     * @return array<int, float>  cart id => value
     */
    public function values(Collection $carts): array
    {
        $ids = $carts->flatMap(fn (Cart $cart) => $cart->items->pluck('product_id'))->map(fn ($id) => (int) $id)->unique()->values()->all();
        $products = $this->products->forCart($ids);

        return $carts->mapWithKeys(fn (Cart $cart) => [$cart->id => round($cart->items->sum(function (CartItem $item) use ($products): float {
            $product = $products->get($item->product_id);

            return $product !== null && $this->cart->problem($product, (int) $item->quantity, $item->components) === null
                ? $this->pricing->for($product)->amount * (int) $item->quantity
                : 0.0;
        }), 2)])->all();
    }

    /**
     * A cart that became an order after a reminder went out.
     */
    public function recovered(Cart $cart): bool
    {
        return $cart->order_id !== null && $cart->reminders()->where('status', CartReminder::SENT)->exists();
    }

    /**
     * A reminder's link (signed, checked by the route): this browser gets the cart back. A cart of
     * an account opens only for that account; an ordered one is not reopened.
     *
     * @return self::RESTORED|self::ORDERED|self::SIGN_IN|self::FORBIDDEN|self::MISSING
     */
    public function restore(Request $request, string $uuid): string
    {
        $cart = $this->carts->findByUuid($uuid);

        if ($cart === null) {
            return self::MISSING;
        }

        if ($cart->order_id !== null) {
            return self::ORDERED;
        }

        $userId = $request->user()?->getAuthIdentifier();

        if ($cart->user_id !== null && (int) $cart->user_id !== (int) $userId) {
            return $userId === null ? self::SIGN_IN : self::FORBIDDEN;
        }

        $cart->update(['last_activity_at' => Carbon::now()]);

        // Signed in with a guest's cart: it joins the account as on sign-in (merged into the
        // account's open cart, or made the account's), never served to it unowned.
        if ($userId !== null && $cart->user_id === null) {
            $this->cart->adoptCart($cart, $request->user(), CartCookie::token($request));

            return self::RESTORED;
        }

        CartCookie::queue($cart->token);

        return self::RESTORED;
    }
}
