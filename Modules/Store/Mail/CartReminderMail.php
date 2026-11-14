<?php

namespace Modules\Store\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Modules\Store\Entities\Cart;

/**
 * "Your cart is waiting for you": the items still in the cart, priced now, with a link that brings
 * the cart back to any browser and one that stops these messages. Sent in the cart's language
 * (CartReminderSender sets the locale).
 */
class CartReminderMail extends Mailable
{
    use Queueable;

    /**
     * @param  list<array<string, mixed>>  $lines  CartService::lines(), the ones that can be bought
     */
    public function __construct(
        public readonly Cart $cart,
        public readonly array $lines,
        public readonly string $total,
        public readonly string $restoreUrl,
        public readonly string $unsubscribeUrl,
        public readonly string $store,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Your cart is waiting for you'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'store::mail.cart-reminder');
    }
}
