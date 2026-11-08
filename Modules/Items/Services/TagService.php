<?php

namespace Modules\Items\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Modules\Core\Support\Translations;
use Modules\Items\Entities\Tag;
use Modules\Items\Repositories\Contracts\TagRepositoryInterface;

/**
 * Writes tags: columns and the translation of one locale, together.
 */
class TagService
{
    private const COLUMNS = ['slug', 'text_color', 'background_color'];

    private const TRANSLATED = ['name', 'description'];

    public function __construct(private readonly TagRepositoryInterface $tags) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, string $locale, ?int $userId): Tag
    {
        return DB::transaction(function () use ($data, $locale, $userId): Tag {
            /** @var Tag $tag */
            $tag = $this->tags->query()->newModelInstance();
            $tag->fill(['created_by' => $userId, ...Arr::only($data, self::COLUMNS)]);
            Translations::fill($tag, Translations::fromForm($data, $locale, self::TRANSLATED));
            $tag->save();

            return $tag;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Tag $tag, array $data, string $locale): Tag
    {
        return DB::transaction(function () use ($tag, $data, $locale): Tag {
            $tag->fill(Arr::only($data, self::COLUMNS));
            Translations::fill($tag, Translations::fromForm($data, $locale, self::TRANSLATED));
            $tag->save();

            return $tag;
        });
    }

    public function delete(Tag $tag): void
    {
        $this->tags->delete($tag);
    }

    /**
     * @param  list<int>  $ids
     */
    public function deleteMany(array $ids): void
    {
        DB::transaction(fn () => $this->tags->query()->whereKey($ids)->get()->each(
            fn (Tag $tag) => $this->tags->delete($tag)
        ));
    }
}
