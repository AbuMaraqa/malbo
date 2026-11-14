<?php

namespace Modules\Store\Support;

/**
 * Phone numbers as buyers type them: "0599 123 456", "+970599123456", "00972-59-912-3456" are the
 * same line. normalize() keeps the digits (Arabic-Indic digits too), drops an international
 * prefix (00 or +) with the Palestinian or Israeli country code, then one leading 0.
 */
final class Phone
{
    private const COUNTRY_CODES = ['970', '972'];

    public static function normalize(?string $phone): string
    {
        $phone = strtr((string) $phone, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);
        $digits = (string) preg_replace('/\D+/', '', $phone);

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        foreach (self::COUNTRY_CODES as $code) {
            // A local number is 9 or 10 digits: only a longer one starts with a country code.
            if (str_starts_with($digits, $code) && strlen($digits) > 10) {
                $digits = substr($digits, strlen($code));
                break;
            }
        }

        return str_starts_with($digits, '0') ? substr($digits, 1) : $digits;
    }

    /**
     * How an order keeps the number: one leading 0 and the national digits (0599123456), whatever
     * was typed; the typed text when it holds no digits.
     */
    public static function canonical(string $phone): string
    {
        $digits = self::normalize($phone);

        return $digits === '' ? trim($phone) : '0'.$digits;
    }

    /**
     * Whether it can be a phone number: 7 to 12 national digits (what checkout accepts).
     */
    public static function plausible(?string $phone): bool
    {
        $digits = strlen(self::normalize($phone));

        return $digits >= 7 && $digits <= 12;
    }

    /**
     * Whether two typed numbers are the same line. Two empty numbers are not.
     */
    public static function same(?string $a, ?string $b): bool
    {
        $a = self::normalize($a);

        return $a !== '' && $a === self::normalize($b);
    }
}
