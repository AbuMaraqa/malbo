<?php

namespace Modules\Items\Services;

use Illuminate\Support\Facades\DB;
use Modules\Core\Support\Translations;
use Modules\Items\Entities\Attribute;
use Modules\Items\Entities\AttributeValue;
use Modules\Items\Enums\AttributeType;
use Modules\Items\Repositories\Contracts\AttributeRepositoryInterface;

/**
 * Writes attributes and their values, with the translations of one locale, together.
 */
class AttributeService
{
    private const TRANSLATED = ['name', 'description'];

    public function __construct(private readonly AttributeRepositoryInterface $attributes) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, string $locale, ?int $userId): Attribute
    {
        return DB::transaction(function () use ($data, $locale, $userId): Attribute {
            /** @var Attribute $attribute */
            $attribute = $this->attributes->query()->newModelInstance();
            $attribute->fill(['created_by' => $userId, 'slug' => $data['slug'], 'type' => $data['type']]);
            Translations::fill($attribute, Translations::fromForm($data, $locale, self::TRANSLATED));
            $attribute->save();

            $this->syncValues($attribute, $data['values'] ?? [], $locale, $userId);

            return $attribute;
        });
    }

    /**
     * Values the form no longer holds are deleted, so a request without `values` leaves them alone.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Attribute $attribute, array $data, string $locale, ?int $userId): Attribute
    {
        return DB::transaction(function () use ($attribute, $data, $locale, $userId): Attribute {
            $attribute->fill(['slug' => $data['slug'], 'type' => $data['type']]);
            Translations::fill($attribute, Translations::fromForm($data, $locale, self::TRANSLATED));
            $attribute->save();

            if (array_key_exists('values', $data)) {
                $this->syncValues($attribute, $data['values'] ?? [], $locale, $userId);
            }

            return $attribute;
        });
    }

    /**
     * Saves the order of a drag: each value's position in $ids becomes its sort order.
     *
     * @param  list<int>  $ids
     */
    public function reorderValues(Attribute $attribute, array $ids): void
    {
        DB::transaction(function () use ($attribute, $ids): void {
            $values = $attribute->attributeValues()->get()->keyBy('id');

            foreach (array_values($ids) as $position => $id) {
                $values->get($id)?->update(['sort_order' => $position]);
            }
        });
    }

    public function delete(Attribute $attribute): void
    {
        $this->attributes->delete($attribute);
    }

    /**
     * @param  list<int>  $ids
     */
    public function deleteMany(array $ids): void
    {
        DB::transaction(fn () => $this->attributes->query()->whereKey($ids)->get()->each(
            fn (Attribute $attribute) => $this->attributes->delete($attribute)
        ));
    }

    /**
     * The attribute's values become the given rows, in their order. A color only belongs to a color attribute.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function syncValues(Attribute $attribute, array $rows, string $locale, ?int $userId): void
    {
        // validated() builds the list in rule order, so a row with an id can come first: the key is its position.
        ksort($rows);

        $existing = $attribute->attributeValues()->with('translations')->get()->keyBy('id');
        $kept = array_filter(array_column($rows, 'id'));
        $isColor = (int) $attribute->type === AttributeType::COLOR->value;

        $existing->except($kept)->each(fn (AttributeValue $value) => $value->delete());

        foreach (array_values($rows) as $position => $row) {
            /** @var AttributeValue $value */
            $value = $existing->get($row['id'] ?? null) ?? $attribute->attributeValues()->make(['created_by' => $userId]);

            $value->fill([
                'color_code' => $isColor ? ($row['color_code'] ?? null) : null,
                'sort_order' => $position,
            ]);
            Translations::fill($value, Translations::fromForm($row, $locale, self::TRANSLATED));
            $value->save();
        }
    }
}
