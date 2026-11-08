<?php

namespace Tests\Feature\Platform;

use App\Models\Tenant;
use App\Platform\Provisioning\SlugAvailability;
use Tests\CentralTestCase;

class SlugAvailabilityTest extends CentralTestCase
{
    public function test_format(): void
    {
        $this->assertSame('format', SlugAvailability::check('ab'));
        $this->assertSame('format', SlugAvailability::check('-abc'));
        $this->assertSame('format', SlugAvailability::check('abc-'));
        $this->assertSame('format', SlugAvailability::check('ab--cd'));
        $this->assertSame('format', SlugAvailability::check('my_shop'));
        $this->assertSame('format', SlugAvailability::check(str_repeat('a', 31)));
        $this->assertNull(SlugAvailability::check(str_repeat('a', 30)));
        $this->assertNull(SlugAvailability::check('my-shop'));
        $this->assertSame('my-shop', SlugAvailability::normalize('  My-Shop '));
    }

    public function test_reserved(): void
    {
        $this->assertSame('reserved', SlugAvailability::check('admin'));
        $this->assertSame('reserved', SlugAvailability::check('platform'));
        $this->assertSame('reserved', SlugAvailability::check('demo-x'));
    }

    public function test_taken(): void
    {
        Tenant::withoutEvents(fn () => Tenant::create(['id' => 'acme']));

        $this->assertSame('taken', SlugAvailability::check('acme'));
    }

    public function test_every_reason_has_a_message(): void
    {
        foreach (['format', 'reserved', 'taken'] as $reason) {
            $this->assertNotSame('', SlugAvailability::message($reason));
        }
    }
}
