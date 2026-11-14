<?php

namespace Modules\Store\Support;

use Illuminate\Support\Facades\Log;
use Modules\Store\Contracts\SmsGateway;

/**
 * No SMS provider yet: nothing is sent (enabled() is false, so callers skip phone-only contacts).
 * A call that still arrives is logged without the number (all but its last 3 digits hidden) and
 * without the text, which holds signed links that open a cart or opt a contact out.
 */
class LogSmsGateway implements SmsGateway
{
    public function enabled(): bool
    {
        return false;
    }

    public function send(string $phone, string $message): void
    {
        $digits = (string) preg_replace('/\D+/', '', $phone);
        $masked = str_repeat('*', max(strlen($digits) - 3, 0)).substr($digits, -3);

        Log::info('SMS not sent (log driver) to '.$masked.': '.mb_strlen($message).' characters');
    }
}
