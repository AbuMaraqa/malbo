<?php

namespace Modules\Wholesale\Services;

use Illuminate\Support\Collection;
use Modules\Items\Contracts\OrderLinePricer;
use Modules\Items\Entities\Order;
use Modules\Items\Enums\OrderChannel;
use Modules\Items\Services\RetailOrderLinePricer;
use Modules\Wholesale\Repositories\Contracts\MerchantRepositoryInterface;
use Modules\Wholesale\Support\PriceBook;

/**
 * Prices staff's new or changed lines on a wholesale (B2B) order at the merchant's prices: its tier,
 * and the quantity break each product reaches over the order's pieces of it. Anything else (a
 * storefront order, a new admin order, a product not sold wholesale) is priced at retail.
 */
class WholesaleOrderLinePricer implements OrderLinePricer
{
    public function __construct(
        private readonly RetailOrderLinePricer $retail,
        private readonly MerchantRepositoryInterface $merchants,
        private readonly WholesalePricing $pricing,
    ) {}

    public function prices(?Order $order, Collection $products, array $quantities): array
    {
        $prices = $this->retail->prices($order, $products, $quantities);

        if ($order === null || $order->channel !== OrderChannel::B2B || ! $order->user_id) {
            return $prices;
        }

        $merchant = $this->merchants->findForUser((int) $order->user_id);

        if ($merchant === null) {
            return $prices;
        }

        $book = $this->pricing->book($this->pricing->tierFor($merchant), $products->values());

        $pieces = [];
        foreach ($quantities as $productId => $quantity) {
            if ($product = $products->get($productId)) {
                $pieces[PriceBook::groupId($product)] = ($pieces[PriceBook::groupId($product)] ?? 0) + (int) $quantity;
            }
        }

        foreach ($products as $productId => $product) {
            $unit = $book->unit($product, $pieces[PriceBook::groupId($product)] ?? 0);

            if ($unit !== null) {
                $prices[(int) $productId] = $unit;
            }
        }

        return $prices;
    }
}
