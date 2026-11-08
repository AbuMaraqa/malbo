<?php

namespace Modules\Items\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Modules\Core\Support\Translations;
use Modules\Items\Entities\Brand;
use Modules\Items\Repositories\Contracts\BrandRepositoryInterface;

/**
 * Writes brands: the slug, the translation of one locale and the image, together.
 */
class BrandService
{
    private const TRANSLATED = ['name', 'description'];

    public function __construct(private readonly BrandRepositoryInterface $brands) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?UploadedFile $image, string $locale, ?int $userId): Brand
    {
        return DB::transaction(function () use ($data, $image, $locale, $userId): Brand {
            /** @var Brand $brand */
            $brand = $this->brands->query()->newModelInstance();
            $brand->fill(['created_by' => $userId, 'slug' => $data['slug']]);
            Translations::fill($brand, Translations::fromForm($data, $locale, self::TRANSLATED));
            $brand->save();

            if ($image) {
                $brand->addImage($image);
            }

            return $brand;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int|string>  $removedImages  ids of saved images the form removed
     */
    public function update(Brand $brand, array $data, ?UploadedFile $image, array $removedImages, string $locale): Brand
    {
        return DB::transaction(function () use ($brand, $data, $image, $removedImages, $locale): Brand {
            $brand->fill(['slug' => $data['slug']]);
            Translations::fill($brand, Translations::fromForm($data, $locale, self::TRANSLATED));
            $brand->save();

            if ($image) {
                $brand->addImage($image); // the collection holds one file: the old one goes
            } elseif ($removedImages !== []) {
                $brand->getMedia('brand')->whereIn('id', $removedImages)->each->delete();
            }

            return $brand;
        });
    }

    public function delete(Brand $brand): void
    {
        $this->brands->delete($brand);
    }

    /**
     * @param  list<int>  $ids
     */
    public function deleteMany(array $ids): void
    {
        DB::transaction(fn () => $this->brands->query()->whereKey($ids)->get()->each(
            fn (Brand $brand) => $this->brands->delete($brand)
        ));
    }
}
