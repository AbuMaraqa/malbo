<?php

namespace Tests\Feature\E2E;

use Modules\Core\Entities\User;
use Modules\Items\Entities\Product;
use Modules\Items\Entities\ReturnRequest;
use Modules\Items\Enums\OrderStatus;
use Modules\Items\Enums\ReturnRequestStatus;
use Modules\Store\Entities\Review;
use Modules\Store\Enums\ReviewStatus;

/**
 * A buyer with an account: signs up, keeps an address and a wishlist, orders, reviews what was
 * delivered, returns one piece for a refund and exchanges another for a different size.
 */
class AccountJourneyTest extends E2ETestCase
{
    public function test_a_member_orders_reviews_returns_and_exchanges(): void
    {
        $demo = $this->demo();
        $polo = $demo->item('polo', 'navy', 'L');
        $chinos = $demo->item('chinos', 'beige', '32');
        $center = $demo->cities['center'];

        // Sign up, sign out, sign back in.
        $this->from($this->siteUrl('/ar/account/register'))->post($this->siteUrl('/ar/account/register'), [
            'name' => 'مازن', 'phone' => '0599 444 555', 'email' => 'Mazen@Example.com', 'password' => 'Secret-pass-123', 'password_confirmation' => 'Secret-pass-123',
        ])->assertSessionHasNoErrors();
        $member = User::query()->where('email', 'mazen@example.com')->sole();
        $this->assertAuthenticatedAs($member);
        $this->assertTrue($member->hasRole('Customer'));
        $this->post($this->siteUrl('/ar/account/logout'));
        $this->assertGuest();
        $this->from($this->siteUrl('/ar/account/login'))->post($this->siteUrl('/ar/account/login'), ['email' => 'mazen@example.com', 'password' => 'Secret-pass-123'])->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($member);

        // An address and a wishlist.
        $this->from($this->siteUrl('/ar/account/addresses'))->post($this->siteUrl('/ar/account/addresses'), [
            'label' => 'البيت', 'recipient_name' => 'مازن', 'phone' => '0599444555', 'city_id' => $center->id,
            'district_id' => $center->districts()->value('id'), 'address_line' => 'حي الماصيون', 'is_default' => true,
        ])->assertSessionHasNoErrors();
        $this->assertCount(1, $this->page('/ar/account/addresses', 'Store/Storefront/Account/Addresses', $member)['addresses']);

        $this->actingAs($member)->post($this->siteUrl('/ar/wishlist'), ['product_id' => $demo->products['bomber']->id])->assertSessionHasNoErrors();
        $wishlist = $this->page('/ar/account/wishlist', 'Store/Storefront/Account/Wishlist', $member);
        $this->assertSame([$demo->products['bomber']->id], array_column($wishlist['cards'], 'id'));

        // Order two polos and chinos with the welcome coupon.
        $cart = $this->addToCart($polo, 2, as: $member);
        $this->addToCart($chinos, 1, $cart, $member);
        $poloStock = $this->stock($polo);
        $chinosStock = $this->stock($chinos);
        $this->checkout($cart, ['name' => 'مازن', 'phone' => '0599444555', 'email' => 'mazen@example.com', 'coupon' => $demo->coupon], $member)->assertSessionHasNoErrors();
        $order = $this->lastOrder();
        $this->assertSame($member->id, $order->user_id);
        $this->assertSame(['387.00', '38.70', '0.00', '348.30'], [$order->sub_total, $order->discount, $order->delivery_fee, $order->total]);

        $orders = $this->page('/ar/account/orders', 'Store/Storefront/Account/Orders', $member);
        $this->assertSame([$order->order_number], array_column($orders['orders']['data'] ?? $orders['orders'], 'number'));
        $this->page('/ar/account/orders/'.$order->order_number, 'Store/Storefront/Account/OrderDetail', $member);

        // No review before delivery; after it, a review waits for staff, who approve it.
        $review = fn () => $this->actingAs($member)->from($this->siteUrl('/ar/product/polo'))->post($this->siteUrl('/ar/product/'.$demo->products['polo']->slug.'/reviews'), ['rating' => 4, 'title' => 'جيد', 'body' => 'خامة ممتازة ومقاس مضبوط']);
        $review()->assertSessionHasErrors('review');

        $this->deliverInAdmin($order);
        $review()->assertSessionHasNoErrors();
        $saved = Review::query()->where('user_id', $member->id)->sole();
        $this->assertSame(ReviewStatus::PENDING, $saved->status);
        $this->assertNotContains($saved->id, $this->reviewIds($demo->products['polo']));

        $this->actingAs($this->admin())->put($this->siteUrl('/ar/admin/store/reviews/'.$saved->id.'/status'), ['status' => 'approved'])->assertSessionHasNoErrors();
        $this->assertContains($saved->id, $this->reviewIds($demo->products['polo']));

        // Return the chinos: back in stock once completed, refunded at their price less their share of the coupon.
        $chinosLine = $order->items->firstWhere('product_id', $chinos->id);
        $this->returnRequest($member, $order->order_number, ['type' => 'return', 'reason' => 'wrong_size', 'lines' => [['order_item_id' => $chinosLine->id, 'quantity' => 1, 'exchange_product_id' => null]]]);
        $return = ReturnRequest::query()->where('order_id', $order->id)->sole();
        $this->assertSame(ReturnRequestStatus::REQUESTED, $return->status);

        foreach (['approved', 'received'] as $status) {
            $this->staffMoves($return, $status);
            $this->assertSame($chinosStock - 1, $this->stock($chinos), 'nothing moves before completion');
        }

        $this->staffMoves($return, 'completed');
        $this->assertSame($chinosStock, $this->stock($chinos));
        $this->assertSame('170.10', $return->fresh()->refund_amount, '189 less 189/387 of the 38.70 discount');
        $this->assertSame(OrderStatus::DELIVERED, $order->fresh()->status, 'the polos are still with the buyer');

        // Exchange one polo for another size the store has.
        $other = collect($demo->items)->filter(fn (Product $item, string $key) => str_starts_with($key, 'polo.navy.') && $item->id !== $polo->id && $this->stock($item) > 0)->first();
        $this->assertNotNull($other, 'the demo has another navy polo size in stock');
        $otherStock = $this->stock($other);
        $poloLine = $order->items->firstWhere('product_id', $polo->id);

        $this->returnRequest($member, $order->order_number, ['type' => 'exchange', 'reason' => 'wrong_size', 'lines' => [['order_item_id' => $poloLine->id, 'quantity' => 1, 'exchange_product_id' => $other->id]]]);
        $exchange = ReturnRequest::query()->where('order_id', $order->id)->latest('id')->firstOrFail();

        foreach (['approved', 'received', 'completed'] as $status) {
            $this->staffMoves($exchange, $status);
        }

        $this->assertSame($poloStock - 2 + 1, $this->stock($polo), 'the polo sent back');
        $this->assertSame($otherStock - 1, $this->stock($other), 'the size given');
        $this->assertSame('0.00', $exchange->fresh()->refund_amount);
        $this->assertSame(['sale' => -3 - 1, 'return' => 2], $this->movements($order));

        // The last polo back closes the order as exchanged (part of it was an exchange).
        $this->returnRequest($member, $order->order_number, ['type' => 'return', 'reason' => 'changed_mind', 'lines' => [['order_item_id' => $poloLine->id, 'quantity' => 1, 'exchange_product_id' => null]]]);
        $last = ReturnRequest::query()->where('order_id', $order->id)->latest('id')->firstOrFail();

        foreach (['approved', 'received', 'completed'] as $status) {
            $this->staffMoves($last, $status);
        }

        $this->assertSame(OrderStatus::EXCHANGED, $order->fresh()->status);
        $this->assertSame($poloStock, $this->stock($polo));
        $this->assertSame('89.10', $last->fresh()->refund_amount, '99 less 99/387 of the discount');
        $this->assertSame(['sale' => -4, 'return' => 3], $this->movements($order), 'the order closing gave nothing back twice');
    }

    private function returnRequest(User $member, string $number, array $fields): void
    {
        $url = $this->siteUrl('/ar/account/orders/'.$number);

        $this->actingAs($member)->from($url)->post($url.'/returns', ['customer_note' => null, ...$fields])->assertSessionHasNoErrors();
    }

    private function staffMoves(ReturnRequest $request, string $status): void
    {
        $this->actingAs($this->admin())->from($this->siteUrl('/ar/admin/return-requests/'.$request->id.'/edit'))
            ->put($this->siteUrl('/ar/admin/return-requests/'.$request->id.'/status'), ['status' => $status, 'note' => null])
            ->assertSessionHasNoErrors();
        $this->assertSame($status, $request->fresh()->status->value);
    }

    /**
     * @return list<int>
     */
    private function reviewIds(Product $product): array
    {
        $this->app['auth']->forgetGuards();
        $reviews = $this->page('/ar/product/'.$product->slug, 'Store/Storefront/ProductPage')['reviews'];

        return array_column($reviews['data'] ?? $reviews, 'id');
    }
}
