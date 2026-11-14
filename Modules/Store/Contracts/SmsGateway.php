<?php

namespace Modules\Store\Contracts;

/**
 * Sends a text message. Only the `log` driver ships (Modules\Store\Support\LogSmsGateway), and it
 * sends nothing: it says so with enabled() false, so callers neither claim a reminder or an alert
 * nor count it as sent. A real provider implements this and is bound in its place.
 */
interface SmsGateway
{
    /**
     * Whether messages really leave: false for the log driver.
     */
    public function enabled(): bool;

    /**
     * @param  string  $phone  as the order keeps it (Phone::canonical: 0599123456)
     *
     * @throws \Throwable when the provider refuses it; callers catch and log
     */
    public function send(string $phone, string $message): void;
}
