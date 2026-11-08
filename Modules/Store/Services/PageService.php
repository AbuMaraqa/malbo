<?php

namespace Modules\Store\Services;

use Modules\Core\Support\RichText;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Core\Support\Translations;
use Modules\Store\Entities\Page;
use Modules\Store\Repositories\Contracts\PageRepositoryInterface;

/**
 * Writes store pages. Bodies are sanitized here; a system page keeps its slug (links to the
 * policies must not break) and cannot be deleted, only hidden.
 */
class PageService
{
    private const ATTRIBUTES = ['slug', 'is_published', 'show_in_footer', 'sort_order'];

    public function __construct(private PageRepositoryInterface $pages) {}

    /**
     * @param  array<string, mixed>  $attributes  slug, is_published, show_in_footer, sort_order
     * @param  array<string, array<string, mixed>>  $translations  locale => [title, body, meta_description]
     */
    public function create(array $attributes, array $translations, ?int $createdBy = null): Page
    {
        $attributes = array_intersect_key($attributes, array_flip(self::ATTRIBUTES));
        $title = (string) collect($translations)->pluck('title')->filter()->first();
        $attributes['slug'] = $this->pages->uniqueSlug((string) ($attributes['slug'] ?? '') ?: $title);

        return DB::transaction(function () use ($attributes, $translations, $createdBy) {
            /** @var Page $page */
            $page = $this->pages->create([...$attributes, 'created_by' => $createdBy]);
            Translations::fill($page, self::clean($translations));
            $page->save();

            return $page;
        });
    }

    /**
     * `created_by` is never changed by an update.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, array<string, mixed>>  $translations
     */
    public function update(Page $page, array $attributes, array $translations): Page
    {
        $attributes = array_intersect_key($attributes, array_flip(self::ATTRIBUTES));

        if ($page->isSystem()) {
            unset($attributes['slug']);
        } elseif (array_key_exists('slug', $attributes)) {
            $attributes['slug'] = $this->pages->uniqueSlug((string) $attributes['slug'] ?: $page->slug, $page->id);
        }

        return DB::transaction(function () use ($page, $attributes, $translations) {
            Translations::fill($page, self::clean($translations));

            return $this->pages->update($page, $attributes);
        });
    }

    public function setPublished(Page $page, bool $published): Page
    {
        return $this->pages->update($page, ['is_published' => $published]);
    }

    public function setInFooter(Page $page, bool $inFooter): Page
    {
        return $this->pages->update($page, ['show_in_footer' => $inFooter]);
    }

    public function delete(Page $page): void
    {
        if ($page->isSystem()) {
            throw new InvalidArgumentException('System pages cannot be deleted; unpublish them instead.');
        }

        $this->pages->delete($page);
    }

    /**
     * Trimmed texts and a sanitized body; a locale without a title is left out (the other stands in).
     */
    private static function clean(array $translations): array
    {
        $clean = [];

        foreach ($translations as $locale => $fields) {
            foreach (['title', 'meta_description'] as $field) {
                if (array_key_exists($field, $fields)) {
                    $fields[$field] = filled($fields[$field]) ? trim((string) $fields[$field]) : null;
                }
            }
            if (array_key_exists('body', $fields)) {
                $fields['body'] = filled($fields['body']) ? RichText::make($fields['body'])->toHtml() : null;
            }
            if (array_key_exists('title', $fields) && $fields['title'] === null) {
                continue;
            }

            $clean[$locale] = $fields;
        }

        return $clean;
    }
}
