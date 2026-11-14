<?php

namespace Tests\Unit\Store;

use Modules\Store\Support\Phone;
use PHPUnit\Framework\TestCase;

class PhoneTest extends TestCase
{
    public function test_normalize(): void
    {
        foreach (['0599123456', '599123456', '+970599123456', '00970 599 123 456', '972-59-912-3456', '(0599) 123-456', '٠٥٩٩١٢٣٤٥٦'] as $phone) {
            $this->assertSame('599123456', Phone::normalize($phone), $phone);
        }

        $this->assertSame('', Phone::normalize('phone'));
        $this->assertNotSame(Phone::normalize('0599123456'), Phone::normalize('0599123457'));
        $this->assertTrue(Phone::same('+970 599 123 456', '0599123456'));
        $this->assertFalse(Phone::same('', ''));
    }
}
