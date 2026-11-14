<?php

namespace Tests\Feature\Core;

use Illuminate\Support\Facades\URL;
use Tests\DatabaseTestCase;

/**
 * The client address that rate limits count by. X-Forwarded-For is only believed from the proxies
 * in TRUSTED_PROXIES (config `trustedproxy.proxies`); with none set, a buyer cannot dodge a limit
 * by sending a new header with every request.
 */
class TrustedProxiesTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        URL::defaults(['locale' => 'ar']);
        app()->setLocale('ar');
    }

    public function test_a_spoofed_forwarded_for_does_not_change_the_rate_limit_key(): void
    {
        config(['trustedproxy.proxies' => null]);

        for ($i = 1; $i <= 10; $i++) {
            $this->lookup("203.0.113.{$i}");
        }

        $this->lookup('203.0.113.99')->assertSessionHasErrors('number');
        $this->assertStringContainsString('محاولات', session('errors')->first('number'));
    }

    public function test_a_trusted_proxy_passes_the_client_address_on(): void
    {
        config(['trustedproxy.proxies' => '127.0.0.1']);

        for ($i = 1; $i <= 10; $i++) {
            $this->lookup("203.0.113.{$i}");
        }

        $this->lookup('203.0.113.99')->assertSessionHasErrors('number');
        $this->assertStringNotContainsString('محاولات', session('errors')->first('number'));
    }

    private function lookup(string $forwardedFor)
    {
        return $this->withHeader('X-Forwarded-For', $forwardedFor)
            ->from($this->siteUrl('/ar/track'))
            ->post($this->siteUrl('/ar/track'), ['number' => 'NOPE', 'phone' => '0599000000']);
    }
}
