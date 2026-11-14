<?php

namespace Tests\Feature\Store\Concerns;

use Illuminate\Support\Str;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;
use Modules\GeoLocation\Entities\City;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\OrderChannel;
use Modules\Items\Enums\OrderStatus;
use Modules\Items\Services\OrderService;
use Modules\Store\Entities\Review;
use Modules\Store\Enums\ReviewStatus;

/**
 * Buyers, their orders in a given status, and reviews written straight to the table.
 */
trait MakesReviews
{
    private ?City $reviewCity = null;

    protected function buyer(string $name = 'Samir Haddad'): User
    {
        return User::forceCreate(['name' => $name, 'email' => 'buyer-'.Str::lower(Str::random(8)).'@example.com', 'password' => 'secret-password', 'email_verified_at' => now()])
            ->assignRole(UserRole::CUSTOMER->value);
    }

    protected function orderFor(?User $user, Product $item, OrderStatus $status = OrderStatus::DELIVERED, OrderChannel $channel = OrderChannel::B2C): Order
    {
        $this->reviewCity ??= $this->city();

        $order = app(OrderService::class)->checkout([
            'user_id' => $user?->id,
            'customer_name' => $user?->name ?? 'Guest',
            'contact_phone' => '0599123456',
            'delivery_address' => 'شارع',
            'city_id' => $this->reviewCity->id,
            'payment_method' => 1,
            'delivery_fee' => 0,
            'discount' => 0,
        ], [['product_id' => $item->id, 'quantity' => 1, 'price' => 100]], $channel);

        Order::query()->whereKey($order->id)->update(['status' => $status]);

        return $order->fresh();
    }

    protected function review(Product $product, ?User $user = null, array $attributes = []): Review
    {
        return Review::query()->create([
            'product_id' => $product->id,
            'user_id' => ($user ?? $this->buyer())->id,
            'rating' => 5,
            'title' => 'ممتاز',
            'body' => 'قماش رائع ومقاس مضبوط.',
            'status' => ReviewStatus::APPROVED,
            ...$attributes,
        ]);
    }
}
