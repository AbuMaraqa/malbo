<?php

namespace Tests\Unit\Core;

use Modules\Core\Services\ImageService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Re-encoding uploaded photos: no file left behind in the temp folder, and an image whose header
 * claims huge dimensions is refused before it is decoded (a "pixel bomb" would take the memory).
 */
class ImageServiceTest extends TestCase
{
    public function test_reencoding_leaves_only_the_new_file(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'src');
        $image = imagecreatetruecolor(40, 30);
        imagejpeg($image, $source);

        $before = $this->tempFiles();
        $target = ImageService::reencode($source, 'jpg');
        $after = $this->tempFiles();

        $this->assertFileExists($target);
        $this->assertSame([realpath($target)], array_values(array_diff($after, $before)));

        @unlink($target);
        @unlink($source);
    }

    public function test_huge_dimensions_are_refused_before_decoding(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'src');
        file_put_contents($source, self::pngHeader(7000, 7000));

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('too large');
            ImageService::reencode($source, 'png');
        } finally {
            @unlink($source);
        }
    }

    /**
     * A PNG that is only a header (signature + IHDR) claiming these dimensions: enough for
     * getimagesize() and the `dimensions` rule, nothing to decode.
     */
    public static function pngHeader(int $width, int $height): string
    {
        $ihdr = 'IHDR'.pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0);

        return "\x89PNG\r\n\x1a\n".pack('N', 13).$ihdr.pack('N', crc32($ihdr))
            .pack('N', 0).'IEND'.pack('N', crc32('IEND'));
    }

    /**
     * @return list<string>
     */
    private function tempFiles(): array
    {
        clearstatcache();

        return array_map('realpath', glob(sys_get_temp_dir().DIRECTORY_SEPARATOR.'img*') ?: []);
    }
}
