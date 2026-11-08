<?php

namespace Modules\Core\Services;

use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageManagerInterface;

class ImageService
{
    /**
     * Imagick where it is installed, GD everywhere else.
     */
    protected static function manager(): ImageManagerInterface
    {
        return ImageManager::usingDriver(extension_loaded('imagick') ? ImagickDriver::class : GdDriver::class);
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
