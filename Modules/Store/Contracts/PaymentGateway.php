<?php

namespace Modules\Store\Contracts;

use Modules\Items\Entities\Order;

/**
 * An online payment provider. Nothing is bound today, so checkout offers cash on delivery only;
 * binding an implementation in a service provider adds "Online payment" to checkout. An order paid
 * this way is placed as new (not awaiting confirmation) and unpaid until the provider confirms it.
 */
interface PaymentGateway
{
    /**
     * The name the buyer sees, e.g. "Card (Visa, Mastercard)".
     */
    public function label(): string;

    /**
     * Starts the payment of a placed order: the address the buyer is sent to.
     */
    public function start(Order $order): string;
}
