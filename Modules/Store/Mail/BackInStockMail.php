<?php

namespace Modules\Store\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * "It is back": the item a buyer asked about can be ordered again. In the language they asked in
 * (StockAlertService sets the locale).
 */
class BackInStockMail extends Mailable
{
    use Queueable;

    public function __construct(
        public readonly string $name,
        public readonly string $options,
        public readonly string $url,
        public readonly string $unsubscribeUrl,
        public readonly string $store,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __(':product is back in stock', ['product' => $this->name]));
    }

    public function content(): Content
    {
        return new Content(markdown: 'store::mail.back-in-stock');
    }
}
