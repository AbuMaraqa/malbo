<?php

namespace Tests\Fakes;

use Modules\Store\Contracts\SmsGateway;

/**
 * Text messages a test sent, instead of a provider.
 */
class FakeSmsGateway implements SmsGateway
{
    /** A provider is set up; false behaves as the log driver does. */
    public bool $enabled = true;

    /** @var list<array{phone: string, message: string}> */
    public array $sent = [];

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function send(string $phone, string $message): void
    {
        $this->sent[] = ['phone' => $phone, 'message' => $message];
    }
}
