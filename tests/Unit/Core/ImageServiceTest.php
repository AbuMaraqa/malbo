<?php

namespace Tests\Unit\Core;

use Modules\Core\Services\ImageService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Entities swallow upload errors, so a broken image service means products silently without images.
 */
class ImageServiceTest extends TestCase
{
    private string $path = '';

    protected function tearDown(): void
    {
        if ($this->path !== '' && is_file($this->path)) {
            unlink($this->path);
        }

        parent::tearDown();
    }

    private function image(string $extension, int $width, int $height): string
    {
        $this->path = tempnam(sys_get_temp_dir(), 'img').'.'.$extension;
        $canvas = imagecreatetruecolor($width, $height);

        match ($extension) {
            'png' => imagepng($canvas, $this->path),
            'webp' => imagewebp($canvas, $this->path),
            default => imagejpeg($canvas, $this->path),
        };

        return $this->path;
    }

    /**
     * @return array<string, array{string}>
     */
    public static function formats(): array
    {
        return ['jpg' => ['jpg'], 'png' => ['png'], 'webp' => ['webp']];
    }

    #[DataProvider('formats')]
    public function test_resize_shrinks_to_fit_and_keeps_the_proportions(string $extension): void
    {
        $path = $this->image($extension, 1600, 800);

        ImageService::resize($path, 800, 800);

        $this->assertSame([800, 400], array_slice(getimagesize($path), 0, 2));
    }

    public function test_resize_does_not_enlarge_a_small_image(): void
    {
        $path = $this->image('jpg', 100, 50);

        ImageService::resize($path, 800, 800);

        $this->assertSame([100, 50], array_slice(getimagesize($path), 0, 2));
    }

    #[DataProvider('formats')]
    public function test_optimize_leaves_a_valid_image(string $extension): void
    {
        $path = $this->image($extension, 200, 200);

        ImageService::optimize($path, 60);

        $this->assertSame([200, 200], array_slice(getimagesize($path), 0, 2));
    }
}
