<?php

namespace Tests\Unit\Reports;

use Modules\Reports\Support\ReportMoney;
use PHPUnit\Framework\TestCase;

class ReportMoneyTest extends TestCase
{
    public function test_formats_numeric_strings_without_floats(): void
    {
        $this->assertSame('1,234,567.50 ₪', ReportMoney::format('1234567.5'));
        $this->assertSame('-0.10 ₪', ReportMoney::format('-0.10'));
        $this->assertSame('0.00 ₪', ReportMoney::format(null));
        $this->assertSame('0.00 ₪', ReportMoney::format('-0.001'));
        $this->assertSame('10.01 ₪', ReportMoney::format('10.005'));
        $this->assertSame('99999999999999.99 ₪', str_replace(',', '', ReportMoney::format('99999999999999.99')));
        $this->assertSame('0.30', ReportMoney::plain('0.3'));
        $this->assertSame('12.00', ReportMoney::plain(12));
        $this->assertSame('0.00', ReportMoney::plain('abc'));
    }
}
