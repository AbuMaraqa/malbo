<?php

namespace Modules\Store\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Core\Services\ImageService;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/**
 * An uploaded picture stored in a model's media collection, as reviews store theirs: decoded and
 * written again (ImageService::reencode: no EXIF or GPS, at most 2000 px a side), so only a real
 * image is kept. Instagram posts and campaign blocks use it.
 */
final class StoreImages
{
    private const FORMATS = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    /**
     * @throws ValidationException on $field when the file is not a readable JPG, PNG or WebP
     */
    public static function store(HasMedia $model, UploadedFile $file, string $collection, string $field): Media
    {
        try {
            $format = self::FORMATS[$file->getMimeType()] ?? throw new \RuntimeException('not an image');
            $path = ImageService::reencode((string) $file->getRealPath(), $format);
        } catch (Throwable) {
            throw ValidationException::withMessages([$field => __('This picture could not be read. Use a JPG, PNG or WebP image.')]);
        }

        return $model->addMedia($path)->usingFileName(Str::uuid().'.'.$format)->toMediaCollection($collection);
    }
}
