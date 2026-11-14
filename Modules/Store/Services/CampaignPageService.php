<?php

namespace Modules\Store\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Modules\Core\Support\RichText;
use Modules\Core\Support\Translations;
use Modules\Store\Entities\CampaignBlock;
use Modules\Store\Entities\CampaignPage;
use Modules\Store\Enums\CampaignBlockType;
use Modules\Store\Repositories\Contracts\CampaignPageRepositoryInterface;
use Modules\Store\Support\StoreImages;

/**
 * Writes campaign pages: the page (slug, publish state and dates), its title and SEO texts in the
 * language being edited, and its blocks in the order sent. A saved block keeps its texts in the other
 * languages and its picture unless a new one is sent; a block left out is deleted (its picture with
 * it). Rich text is made safe here (RichText).
 */
class CampaignPageService
{
    private const TEXTS = ['title', 'seo_title', 'seo_description'];

    /** What each block type keeps in `settings`. */
    private const SETTINGS = ['link', 'source', 'product_ids', 'category_id', 'limit', 'ends_at'];

    public function __construct(private readonly CampaignPageRepositoryInterface $pages) {}

    /**
     * @param  array<string, mixed>  $data  CampaignPageRequest's fields
     */
    public function create(array $data, string $locale, int $userId): CampaignPage
    {
        return DB::transaction(function () use ($data, $locale, $userId): CampaignPage {
            $page = new CampaignPage(['created_by' => $userId]);

            return $this->save($page, $data, $locale);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(CampaignPage $page, array $data, string $locale): CampaignPage
    {
        return DB::transaction(fn () => $this->save($page, $data, $locale));
    }

    /**
     * Its blocks one by one, so their pictures go with them; then the page.
     */
    public function delete(CampaignPage $page): void
    {
        DB::transaction(function () use ($page): void {
            $page->blocks->each(fn (CampaignBlock $block) => $block->delete());
            $this->pages->delete($page);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function save(CampaignPage $page, array $data, string $locale): CampaignPage
    {
        $texts = array_map(fn ($value) => filled($value) ? trim((string) $value) : null, Arr::only($data, self::TEXTS));

        Translations::fill($page, Translations::fromForm($texts, $locale, self::TEXTS));
        $page->fill([
            'slug' => $this->pages->uniqueSlug(filled($data['slug'] ?? null) ? (string) $data['slug'] : (string) $data['title'], $page->exists ? $page->id : null),
            'status' => $data['status'],
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
        ])->save();

        $this->syncBlocks($page, array_values((array) ($data['blocks'] ?? [])), $locale);

        return $page;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function syncBlocks(CampaignPage $page, array $rows, string $locale): void
    {
        $saved = $page->exists ? $page->blocks()->get()->keyBy('id') : collect();
        $kept = [];

        foreach ($rows as $index => $row) {
            $type = CampaignBlockType::from((string) $row['type']);
            // Only this page's own blocks are updated: any other id makes a new block.
            /** @var CampaignBlock|null $block */
            $block = $saved->get((int) ($row['id'] ?? 0));

            if ($block !== null && $block->type !== $type->value) {
                $block = null; // a block never changes type: the old one goes, a new one comes
            }

            $content = (array) ($block?->content ?? []);
            $content[$locale] = self::texts($type, (array) ($row['content'] ?? []));

            $attributes = [
                'type' => $type->value,
                'sort_order' => $index,
                'settings' => self::settings($type, (array) ($row['settings'] ?? [])),
                'content' => $content,
            ];

            $block ? $block->update($attributes) : $block = $page->blocks()->create($attributes);
            $kept[] = $block->id;

            if ($type->hasImage() && ($row['image'] ?? null) instanceof UploadedFile) {
                StoreImages::store($block, $row['image'], 'image', "blocks.{$index}.image");
            }
        }

        $saved->except($kept)->each(fn (CampaignBlock $block) => $block->delete());
    }

    /**
     * The block's texts in the language being edited; rich text made safe.
     *
     * @param  array<string, mixed>  $content
     * @return array<string, string>
     */
    private static function texts(CampaignBlockType $type, array $content): array
    {
        $texts = [];

        foreach ($type->texts() as $field) {
            $value = trim((string) ($content[$field] ?? ''));
            $texts[$field] = $field === 'html' && $value !== '' ? RichText::make($value)->toHtml() : $value;
        }

        return $texts;
    }

    /**
     * What the type uses of the settings sent, as plain values.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private static function settings(CampaignBlockType $type, array $settings): array
    {
        $settings = Arr::only($settings, self::SETTINGS);

        return array_filter(match ($type) {
            CampaignBlockType::IMAGE, CampaignBlockType::BANNER, CampaignBlockType::CTA => ['link' => filled($settings['link'] ?? null) ? trim((string) $settings['link']) : null],
            CampaignBlockType::PRODUCTS => [
                'source' => ($settings['source'] ?? 'picked') === 'category' ? 'category' : 'picked',
                'product_ids' => array_values(array_unique(array_map('intval', (array) ($settings['product_ids'] ?? [])))),
                'category_id' => filled($settings['category_id'] ?? null) ? (int) $settings['category_id'] : null,
                'limit' => (int) ($settings['limit'] ?? 8) ?: 8,
            ],
            CampaignBlockType::COUNTDOWN => ['ends_at' => filled($settings['ends_at'] ?? null) ? (string) $settings['ends_at'] : null],
            CampaignBlockType::INSTAGRAM => ['limit' => (int) ($settings['limit'] ?? 8) ?: 8],
            CampaignBlockType::TEXT => [],
        }, fn ($value) => $value !== null);
    }
}
