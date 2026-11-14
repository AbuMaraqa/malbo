<?php

namespace Modules\Store\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Store\Entities\InstagramPost;
use Modules\Store\Repositories\Contracts\InstagramPostRepositoryInterface;
use Modules\Store\Settings\StoreAppearanceSettings;
use Modules\Store\Support\StoreImages;

/**
 * Writes Instagram posts: the ones staff enter (a picture, the post's link, a caption, the products
 * it shows) and, through import(), the ones a sync brings from Instagram. Also the home section's
 * switch and title.
 */
class InstagramService
{
    private const COLUMNS = ['permalink', 'caption', 'posted_at', 'sort_order', 'is_active'];

    public function __construct(private readonly InstagramPostRepositoryInterface $posts) {}

    /**
     * @param  array<string, mixed>  $data  InstagramPostRequest's fields
     */
    public function create(array $data, int $userId): InstagramPost
    {
        return DB::transaction(function () use ($data, $userId): InstagramPost {
            /** @var InstagramPost $post */
            $post = $this->posts->create([...$this->columns($data), 'source' => InstagramPost::MANUAL, 'created_by' => $userId]);

            return $this->saveRelations($post, $data);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(InstagramPost $post, array $data): InstagramPost
    {
        return DB::transaction(function () use ($post, $data): InstagramPost {
            $this->posts->update($post, $this->columns($data));

            return $this->saveRelations($post, $data);
        });
    }

    public function setActive(InstagramPost $post, bool $active): void
    {
        $this->posts->update($post, ['is_active' => $active]);
    }

    public function delete(InstagramPost $post): void
    {
        // Through the model: its picture goes with it.
        $this->posts->delete($post);
    }

    /**
     * A post brought in by a sync (the Graph API): the source's own id finds it again, so a second
     * run updates it instead of adding another. A post whose link is not an https:// address is
     * skipped (logged, null): the storefront links to it as it is.
     *
     * @param  array{permalink: string, caption?: ?string, posted_at?: ?string, sort_order?: int}  $data
     */
    public function import(string $source, string $externalId, array $data): ?InstagramPost
    {
        $permalink = trim((string) ($data['permalink'] ?? ''));

        if (! str_starts_with(strtolower($permalink), 'https://') || filter_var($permalink, FILTER_VALIDATE_URL) === false) {
            Log::warning('Instagram post skipped: its link is not an https address.', ['source' => $source, 'external_id' => $externalId]);

            return null;
        }

        return DB::transaction(function () use ($source, $externalId, $data): InstagramPost {
            $post = $this->posts->findExternal($source, $externalId);
            $columns = array_intersect_key($data, array_flip(self::COLUMNS));

            if ($post !== null) {
                /** @var InstagramPost */
                return $this->posts->update($post, $columns);
            }

            /** @var InstagramPost */
            return $this->posts->create([...$columns, 'source' => $source, 'external_id' => $externalId]);
        });
    }

    /**
     * The home page's Instagram section: on or off, and its title in $locale (empty: the
     * storefront's own wording).
     */
    public function saveSettings(bool $enabled, ?string $title, string $locale): void
    {
        $settings = app(StoreAppearanceSettings::class);
        $settings->instagram_enabled = $enabled;
        $settings->instagram_title = array_filter([...$settings->instagram_title, $locale => trim((string) $title)], fn ($text) => $text !== '');
        $settings->save();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function columns(array $data): array
    {
        $columns = array_intersect_key($data, array_flip(self::COLUMNS));
        $columns['caption'] = filled($columns['caption'] ?? null) ? trim((string) $columns['caption']) : null;
        $columns['is_active'] = (bool) ($data['is_active'] ?? false);
        $columns['sort_order'] = (int) ($data['sort_order'] ?? 0);

        return $columns;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function saveRelations(InstagramPost $post, array $data): InstagramPost
    {
        if (array_key_exists('products', $data)) {
            $rows = [];

            foreach (array_values(array_unique(array_map('intval', (array) $data['products']))) as $index => $id) {
                $rows[$id] = ['sort_order' => $index];
            }

            $post->products()->sync($rows);
        }

        if (($data['image'] ?? null) instanceof UploadedFile) {
            StoreImages::store($post, $data['image'], 'image', 'image');
        }

        return $post;
    }
}
