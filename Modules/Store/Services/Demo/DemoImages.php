<?php

namespace Modules\Store\Services\Demo;

use Illuminate\Http\UploadedFile;

/**
 * Plain placeholder pictures for the demo (GD): a coloured card with a darker band, so the
 * storefront's grids are not empty. Never shipped as real product photos.
 */
final class DemoImages
{
    public static function available(): bool
    {
        return function_exists('imagecreatetruecolor') && function_exists('imagepng');
    }

    public static function make(string $name, string $hex, int $width = 800, int $height = 1000): UploadedFile
    {
        [$r, $g, $b] = sscanf(ltrim($hex, '#'), '%02x%02x%02x');

        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, $r, $g, $b));

        // A band darker (or lighter, on a dark colour) than the background.
        $shift = ($r + $g + $b) / 3 > 128 ? -40 : 40;
        $band = (int) imagecolorallocate($image, self::clamp($r + $shift), self::clamp($g + $shift), self::clamp($b + $shift));
        imagefilledrectangle($image, 0, (int) ($height * 0.78), $width, $height, $band);
        imagefilledellipse($image, (int) ($width / 2), (int) ($height * 0.4), (int) ($width * 0.55), (int) ($width * 0.55), $band);

        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'malbo-demo-'.bin2hex(random_bytes(8)).'.png';
        imagepng($image, $path);
        imagedestroy($image);

        return new UploadedFile($path, $name.'.png', 'image/png', null, true);
    }

    private static function clamp(int|float $value): int
    {
        return (int) max(0, min(255, $value));
    }
}
