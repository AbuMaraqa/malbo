<?php

namespace Modules\Store\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Modules\Core\Support\Translations;
use Modules\Store\Entities\HeroSlide;
use Modules\Store\Repositories\Contracts\HeroSlideRepositoryInterface;
use Modules\Store\Support\StoreImages;

/**
 * Writes the home slider's slides: the slide (link, order, switch, dates), its texts in the language
 * being edited (the other languages keep theirs) and its pictures. A picture stays unless a new one
 * is sent; the phones' picture can also be removed (the wide one is then used on phones too).
 */
class HeroSlideService
{
    private const TEXTS = ['eyebrow', 'title', 'subtitle', 'button_label'];

    public function __construct(private readonly HeroSlideRepositoryInterface $slides) {}

    /**
     * @param  array<string, mixed>  $data  HeroSlideRequest's fields
     */
    public function create(array $data, string $locale, int $userId): HeroSlide
    {
        return DB::transaction(fn () => $this->save(new HeroSlide(['created_by' => $userId]), $data, $locale));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(HeroSlide $slide, array $data, string $locale): HeroSlide
    {
        return DB::transaction(fn () => $this->save($slide, $data, $locale));
    }

    public function setActive(HeroSlide $slide, bool $active): void
    {
        $this->slides->update($slide, ['is_active' => $active]);
    }

    public function delete(HeroSlide $slide): void
    {
        // Through the model: its pictures and texts go with it.
        $this->slides->delete($slide);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function save(HeroSlide $slide, array $data, string $locale): HeroSlide
    {
        $texts = array_map(fn ($value) => filled($value) ? trim((string) $value) : null, Arr::only($data, self::TEXTS));

        Translations::fill($slide, Translations::fromForm($texts, $locale, self::TEXTS));
        $slide->fill([
            'link' => filled($data['link'] ?? null) ? trim((string) $data['link']) : null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? false),
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
        ])->save();

        if (($data['image'] ?? null) instanceof UploadedFile) {
            StoreImages::store($slide, $data['image'], 'image', 'image');
        }

        if (($data['mobile_image'] ?? null) instanceof UploadedFile) {
            StoreImages::store($slide, $data['mobile_image'], 'mobile_image', 'mobile_image');
        } elseif ($data['remove_mobile_image'] ?? false) {
            $slide->clearMediaCollection('mobile_image');
        }

        return $slide;
    }
}
