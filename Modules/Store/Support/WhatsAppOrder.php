<?php

namespace Modules\Store\Support;

/**
 * "Order on WhatsApp": a wa.me link that opens a chat with the store, the order already typed.
 */
final class WhatsAppOrder
{
    public static function link(string $number, string $text): string
    {
        return 'https://wa.me/'.preg_replace('/\D+/', '', $number).'?text='.rawurlencode($text);
    }

    /**
     * A chat with a buyer. wa.me wants the international number: a local 05… number is taken as
     * Palestinian (970).
     */
    public static function chat(string $phone): string
    {
        $international = str_starts_with($phone, '0') && ! str_starts_with($phone, '00') ? '970'.substr($phone, 1) : ltrim($phone, '0');

        return 'https://wa.me/'.$international;
    }
}
