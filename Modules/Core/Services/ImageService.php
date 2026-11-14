<?php

namespace Modules\Core\Services;

use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageManagerInterface;

class ImageService
{
    /** The most pixels reencode() decodes: 6000 × 6000. */
    public const MAX_PIXELS = 36_000_000;

    /**
     * Imagick where it is installed, GD everywhere else.
     */
    protected static function manager(): ImageManagerInterface
    {
        return ImageManager::usingDriver(extension_loaded('imagick') ? ImagickDriver::class : GdDriver::class);
    }

    /**
     * A new file decoded from $path and encoded again as $format (jpg, png or webp): no metadata
     * (EXIF, GPS, camera) survives, the orientation is applied, the longest side is at most $maxSide.
     * Anything that is not really an image fails to decode and throws. Returns the new file's path.
     */
    public static function reencode(string $path, string $format = 'jpg', int $maxSide = 2000, int $quality = 85): string
    {
        $manager = ImageManager::usingDriver(extension_loaded('imagick') ? ImagickDriver::class : GdDriver::class, autoOrientation: true, decodeAnimation: false, strip: true);
        // Read from the header before decoding: a small file can claim a huge image (a "pixel bomb").
        $size = @getimagesize($path);

        if ($size === false || $size[0] * $size[1] > self::MAX_PIXELS) {
            throw new \RuntimeException($size === false ? 'not an image' : 'image too large');
        }

        // tempnam() creates the file it names: that one goes, the encoded image takes its name plus the extension.
        $base = tempnam(sys_get_temp_dir(), 'img');
        @unlink($base);
        $target = $base.'.'.$format;

        $manager->decodePath($path)->scaleDown($maxSide, $maxSide)->save($target, quality: $quality);

        return $target;
    }

    public static function optimize(string $path, int $quality = 75): void
    {
        self::manager()->decodePath($path)->save($path, quality: $quality);
    }

    /**
     * Shrinks to fit the box, keeping the proportions. A smaller image is left as it is.
     */
    public static function resize(string $path, int $width = 800, int $height = 800): void
    {
        self::manager()->decodePath($path)->scaleDown($width, $height)->save($path);
    }
}
