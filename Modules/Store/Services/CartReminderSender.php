<?php

namespace Modules\Store\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Modules\Core\Settings\GeneralSettings;
use Modules\Store\Contracts\SmsGateway;
use Modules\Store\Entities\Cart;
use Modules\Store\Entities\CartReminder;
use Modules\Store\Entities\MarketingOptOut;
use Modules\Store\Mail\CartReminderMail;
use Modules\Store\Repositories\Contracts\CartRepositoryInterface;
use Modules\Store\Settings\MarketingSettings;
use Modules\Store\Support\InLocale;
use Modules\Store\Support\Price;
use Throwable;

/**
 * Sends the abandoned-cart reminders that are due (`store:send-cart-reminders`, every 10 minutes).
 *
 * Reminder n (at most 2) is due `reminder_delays[n-1]` minutes after the cart's last activity (never
 * before it counts as abandoned) and is sent only in the 48 hours after that: a cart quiet for
 * days is not mailed when reminders are switched on or the scheduler comes back.
 *
 * Never twice: the reminder's row (unique per cart and sequence) is claimed before the message goes,
 * and a run holds a lock. An ordered cart, an opted-out contact, a cart without the buyer's
 * agreement or with nothing left to buy gets nothing. By email when there is one, else by SMS.
 */
class CartReminderSender
{
    public const LOCK = 'store:cart-reminders';

    public const LATE_AFTER_HOURS = 48;

    /** How long a restore link works. */
    public const LINK_DAYS = 14;

    public function __construct(
        private readonly CartRepositoryInterface $carts,
        private readonly CartService $cart,
        private readonly AbandonedCartService $abandoned,
        private readonly OptOutService $optOuts,
        private readonly SmsGateway $sms,
    ) {}

    /**
     * @return int reminders sent
     */
    public function sendDue(): int
    {
        $settings = app(MarketingSettings::class);
        $delays = array_slice(array_values(array_map('intval', $settings->reminder_delays)), 0, 2);

        if (! $settings->cart_reminders_enabled || $delays === []) {
            return 0;
        }

        $lock = Cache::lock(self::LOCK, 600);

        if (! $lock->get()) {
            return 0;
        }

        try {
            $now = Carbon::now();
            $abandonedAfter = max(1, (int) $settings->abandoned_after_minutes);
            $quietSince = $now->copy()->subMinutes(max($abandonedAfter, $delays[0]));
            $activeAfter = $now->copy()->subMinutes(max($abandonedAfter, max($delays)))->subHours(self::LATE_AFTER_HOURS);
            $sent = 0;

            foreach ($this->carts->dueForReminders($quietSince, $activeAfter) as $cart) {
                $sent += $this->remind($cart, $delays, $abandonedAfter, $now) ? 1 : 0;
            }

            return $sent;
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  list<int>  $delays
     */
    private function remind(Cart $cart, array $delays, int $abandonedAfter, CarbonInterface $now): bool
    {
        $sequence = $cart->reminders->count() + 1;

        if ($sequence > count($delays) || $cart->last_activity_at === null) {
            return false;
        }

        $due = $cart->last_activity_at->copy()->addMinutes(max($delays[$sequence - 1], $abandonedAfter));

        if ($now->lt($due) || $now->gt($due->copy()->addHours(self::LATE_AFTER_HOURS))) {
            return false;
        }

        $contact = $this->abandoned->contact($cart);

        if (($contact['email'] === null && $contact['phone'] === null) || $this->optOuts->optedOut($contact['email'], $contact['phone'])) {
            return false;
        }

        // By email when there is one, else by SMS, only once a provider is set up (the log driver
        // sends nothing: such a reminder is neither recorded nor counted as sent).
        $channel = $contact['email'] !== null ? 'mail' : 'sms';
        $recipient = (string) ($contact['email'] ?? $contact['phone']);

        if ($channel === 'sms' && ! $this->sms->enabled()) {
            return false;
        }

        // One reminder a day per address or number, whatever the carts: typed contacts are not
        // confirmed, so a stranger's address cannot be flooded through many carts.
        if ($this->remindedRecently($recipient, $now)) {
            return false;
        }

        $locale = InLocale::valid($cart->locale);
        $message = InLocale::run($locale, fn () => $this->message($cart, $contact, $locale));

        if ($message === null) {
            return false;
        }

        $claimed = CartReminder::query()->insertOrIgnore([
            'cart_id' => $cart->id,
            'sequence' => $sequence,
            'channel' => $channel,
            'recipient' => $recipient,
            'status' => CartReminder::SENDING,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($claimed === 0) {
            return false; // another run has it
        }

        $reminder = CartReminder::query()->where('cart_id', $cart->id)->where('sequence', $sequence)->firstOrFail();

        try {
            if ($channel === 'mail') {
                Mail::to($recipient)->locale($locale)->send($message['mail']);
            } else {
                $this->sms->send($recipient, $message['sms']);
            }

            $reminder->update(['status' => CartReminder::SENT, 'sent_at' => Carbon::now()]);

            return true;
        } catch (Throwable $e) {
            $reminder->update(['status' => CartReminder::FAILED]);
            Log::warning('Cart reminder not sent: '.$e->getMessage(), ['cart' => $cart->id, 'sequence' => $sequence, 'channel' => $channel]);

            return false;
        }
    }

    /**
     * Whether this recipient got (or is getting) a reminder in the last 24 hours, from any cart.
     */
    private function remindedRecently(string $recipient, CarbonInterface $now): bool
    {
        return CartReminder::query()
            ->where('recipient', $recipient)
            ->whereIn('status', [CartReminder::SENT, CartReminder::SENDING])
            ->where('created_at', '>', $now->copy()->subDay())
            ->exists();
    }

    /**
     * The reminder in the cart's language, or null when nothing in the cart can be bought now.
     *
     * @param  array{email: ?string, phone: ?string}  $contact
     * @return array{mail: CartReminderMail, sms: string}|null
     */
    private function message(Cart $cart, array $contact, string $locale): ?array
    {
        $lines = array_values(array_filter($this->cart->lines($cart), fn (array $line) => $line['available']));

        if ($lines === []) {
            return null;
        }

        $store = (string) app(GeneralSettings::class)->site_name;
        // Signed as a path (`signed:relative`): it holds behind any host or proxy; sent on the site's address.
        $restoreUrl = url(URL::temporarySignedRoute('store.cart.restore', Carbon::now()->addDays(self::LINK_DAYS), ['locale' => $locale, 'cart' => $cart->uuid], absolute: false));
        $unsubscribeUrl = $contact['email'] !== null
            ? $this->optOuts->url(MarketingOptOut::EMAIL, $contact['email'], $locale)
            : $this->optOuts->url(MarketingOptOut::PHONE, (string) $contact['phone'], $locale);
        $total = (new Price(CartService::subtotal($lines)))->format();

        return [
            'mail' => new CartReminderMail($cart, $lines, $total, $restoreUrl, $unsubscribeUrl, $store),
            'sms' => __('Your cart at :store is waiting for you: :url', ['store' => $store, 'url' => $restoreUrl])
                ."
".__('Stop these messages: :url', ['url' => $unsubscribeUrl]),
        ];
    }
}
