<?php

namespace Modules\Items\Enums;


enum PaymentMethod: int
{
    case CASH               = 1;
    case CREDIT_CARD        = 2;
    case BANK_TRANSFER      = 3;
    // On the merchant's account (wholesale credit terms): paid later, against its credit limit.
    case ON_ACCOUNT         = 4;

    /**
     * لتجهيز قائمة الخيارات للاستمارات (Forms)
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn(self $case) => [$case->value => $case->getLabel()])
            ->toArray();
    }

    /**
     * لإظهار اسم طريقة الدفع للمستخدم
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::CASH                      => __('الدفع عند الاستلام'),
            self::CREDIT_CARD               => __('بطاقة ائتمانية'),
            self::BANK_TRANSFER             => __('تحويل بنكي'),
            self::ON_ACCOUNT                => __('آجل (على الحساب)'),
        };
    }

    /**
     * لإعطاء لون مميز لكل طريقة دفع في لوحة التحكم
     */
    public function getColor(): string | array | null
    {
        return match ($this) {
            self::CASH          => 'info',    // أزرق فاتح
            self::CREDIT_CARD   => 'primary', // اللون الأساسي
            self::BANK_TRANSFER => 'success', // أخضر
            self::ON_ACCOUNT    => 'warning',
        };
    }
}
