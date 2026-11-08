<?php

namespace Modules\Store\Support;

use Modules\Core\Support\RichText;
use Modules\Core\Settings\GeneralSettings;
use Modules\Store\Settings\StoreContactSettings;

/**
 * Fills a page's {store}, {contact}, {email}, {phone} and {whatsapp} from the store's current
 * settings, so the starter texts stay right after the owner changes them. Values are
 * HTML-escaped.
 *
 * {contact} lists only the channels the store set ("WhatsApp +970…, phone … or email …"), or
 * links to the contact page when there is none; the starter texts use it.
 */
final class PagePlaceholders
{
    /**
     * A page body as HTML, filled in. Sanitized on save; again here for rows written before
     * that (or by hand).
     */
    public static function body(string|array|null $body): string
    {
        return filled($body) ? self::fill(RichText::make($body)->toHtml()) : '';
    }

    public static function fill(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        $contact = app(StoreContactSettings::class);
        $fallback = __('our contact channels');

        return strtr($text, [
            '{store}' => e((string) app(GeneralSettings::class)->site_name),
            '{contact}' => str_contains($text, '{contact}') ? self::contact($contact) : '',
            '{email}' => e($contact->email ?: $fallback),
            '{phone}' => e($contact->phone ?: $fallback),
            '{whatsapp}' => e($contact->whatsapp ? '+'.$contact->whatsapp : $fallback),
        ]);
    }

    private static function contact(StoreContactSettings $contact): string
    {
        $channels = array_values(array_filter([
            $contact->whatsapp ? __('WhatsApp').' <a href="https://wa.me/'.e($contact->whatsapp).'" dir="ltr">+'.e($contact->whatsapp).'</a>' : null,
            $contact->phone ? __('phone').' <a href="tel:'.e($contact->phone).'" dir="ltr">'.e($contact->phone).'</a>' : null,
            $contact->email ? __('email').' <a href="mailto:'.e($contact->email).'">'.e($contact->email).'</a>' : null,
        ]));

        if ($channels === []) {
            // The locale is given: the app API has no {locale} default in its URLs.
            return '<a href="'.e(route('store.contact', ['locale' => app()->getLocale()])).'">'.e(__('our contact page')).'</a>';
        }

        $last = array_pop($channels);

        return $channels === [] ? $last : implode(__(', '), $channels).' '.__('or').' '.$last;
    }
}
