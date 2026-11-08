<?php

namespace Modules\Items\Services;

use Illuminate\Support\Facades\DB;
use Modules\Core\Support\Translations;
use Modules\Items\Entities\Addon;
use Modules\Items\Entities\AddonOption;
use Modules\Items\Repositories\Contracts\AddonRepositoryInterface;

/**
 * Writes addons and their options, with the translations of one locale and the option images, together.
 *
 * Deleting is soft, for addons and options alike: a permanent delete cascades to
 * addon_option_order_item and would take the addons off orders already placed.
 */
class AddonService
{
    private const TRANSLATED = ['name', 'description'];

    public function __construct(private readonly AddonRepositoryInterface $addons) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, string $locale, ?int $userId): Addon
    {
        return DB::transaction(function () use ($data, $locale, $userId): Addon {
            /** @var Addon $addon */
            $addon = $this->addons->query()->newModelInstance();
            $addon->fill(['created_by' => $userId, 'type' => $data['type']]);
            Translations::fill($addon, Translations::fromForm($data, $locale, self::TRANSLATED));
            $addon->save();

            $this->syncOptions($addon, $data['options'] ?? [], $locale);

            return $addon;
        });
    }

    /**
     * The options become the given rows. A multipart form sends no field for an empty list, so no
     * `options` means the user removed them all.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Addon $addon, array $data, string $locale): Addon
    {
        return DB::transaction(function () use ($addon, $data, $locale): Addon {
            $addon->fill(['type' => $data['type']]);
            Translations::fill($addon, Translations::fromForm($data, $locale, self::TRANSLATED));
            $addon->save();

            $this->syncOptions($addon, $data['options'] ?? [], $locale);

            return $addon;
        });
    }

    public function delete(Addon $addon): void
    {
        $this->addons->delete($addon);
    }

    /**
     * @param  list<int>  $ids
     */
    public function deleteMany(array $ids): void
    {
        DB::transaction(fn () => $this->addons->query()->whereKey($ids)->get()->each(
            fn (Addon $addon) => $this->addons->delete($addon)
        ));
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function syncOptions(Addon $addon, array $rows, string $locale): void
    {
        // validated() builds the list in rule order, so a row with an id can come first: the key is its position.
        ksort($rows);

        $existing = $addon->addonOptions()->with('translations')->get()->keyBy('id');

        $existing->except(array_filter(array_column($rows, 'id')))->each(fn (AddonOption $option) => $option->delete());

        foreach ($rows as $row) {
            /** @var AddonOption $option */
            $option = $existing->get($row['id'] ?? null) ?? $addon->addonOptions()->make();

            $option->fill([
                'price' => $row['price'],
                'is_default' => (bool) ($row['is_default'] ?? false),
                'is_quantifiable' => (bool) ($row['is_quantifiable'] ?? false),
            ]);
            Translations::fill($option, Translations::fromForm($row, $locale, self::TRANSLATED));
            $option->save();

            if (! empty($row['image'])) {
                $option->addImage($row['image']); // the collection holds one file: the old one goes
            }
        }
    }
}
