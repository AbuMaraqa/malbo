<?php

namespace Modules\Items\Services;

use Modules\Core\Support\RichText;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Modules\Core\Support\Translations;
use Modules\Items\Entities\AttributeValue;
use Modules\Items\Entities\Product;
use Modules\Items\Repositories\Contracts\ProductRepositoryInterface;
use Modules\Items\Support\ProductSeo;

/**
 * A product's variations (child products): created, updated, restored when a deleted one has
 * the same slug, and deleted when no longer in the list. Called inside ProductService's
 * transaction. Variations always take their parent's status.
 */
class ProductVariations
{
    /** Form keys that are not product columns. */
    private const NOT_COLUMNS = [
        'attribute_values', 'attributes', 'image', 'gallery', 'removed_image', 'removed_gallery', 'gallery_order',
        'category_id', 'id', 'locale', 'meta_title', 'meta_description', 'meta_keywords',
    ];

    public function __construct(private ProductRepositoryInterface $products) {}

    /**
     * @param  array<int|string, array<string, mixed>>  $variations  the form's repeater items
     */
    public function sync(Product $parent, array $variations, string $locale, int $userId): void
    {
        $existing = $parent->variations()->get()->keyBy('id');
        $kept = [];

        foreach ($variations as $data) {
            $id = filled($data['id'] ?? null) ? (int) $data['id'] : null;
            $valueIds = self::valueIds($data['attribute_values'] ?? $data['attributes'] ?? []);
            $fields = $this->fields($parent, $data, $locale, $existing->get($id), $valueIds);

            if ($id && $existing->has($id)) {
                $variation = $existing->get($id);
                $fields['slug'] = $this->products->uniqueSlug($fields['slug'], $id);
            } elseif ($variation = $this->products->trashedVariation($parent, $fields['slug'])) {
                $variation->restore();
            } else {
                $fields['slug'] = $this->products->uniqueSlug($fields['slug']);
                $variation = $this->products->query()->getModel()->newInstance(['created_by' => $userId]);
            }

            $this->save($variation, $fields, $locale);
            $kept[] = $variation->id;

            if ($valueIds !== []) {
                $variation->attributeValues()->sync($valueIds);
            }
            if (! empty($data['category_id'])) {
                $variation->categories()->sync($data['category_id']);
            }
            ProductService::attachMedia($variation, $data);
        }

        foreach ($existing->except($kept) as $removed) {
            $this->products->delete($removed);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function fields(Product $parent, array $data, string $locale, ?Product $current, array $valueIds): array
    {
        $fields = Arr::except($data, self::NOT_COLUMNS);
        $fields = array_map(fn ($value) => $value === '' ? null : $value, $fields);

        if (is_string($fields['description'] ?? null)) {
            $fields['description'] = RichText::make($fields['description'])->toHtml();
        }

        // An empty sku would overwrite the stored one (the column is NOT NULL); new rows get one generated.
        if (blank($fields['sku'] ?? null)) {
            unset($fields['sku']);
        }

        $fields['parent_id'] = $parent->id;
        $fields['status'] = $parent->status; // variations follow their parent
        $fields['meta_data'] = ProductSeo::pack($data, $locale, $current?->meta_data);
        $fields['name'] = filled($fields['name'] ?? null) ? $fields['name'] : $this->name($parent, $valueIds);
        $fields['slug'] = Str::slug(filled($fields['slug'] ?? null) ? $fields['slug'] : $fields['name']);

        return $fields;
    }

    private function save(Product $variation, array $fields, string $locale): void
    {
        $translated = $variation->translatedAttributes;

        Translations::fill($variation, Translations::fromForm(
            $fields,
            $locale,
            array_values(array_filter($translated, fn (string $field) => array_key_exists($field, $fields))),
        ));
        $variation->fill(Arr::except($fields, $translated))->save();
    }

    private function name(Product $parent, array $valueIds): string
    {
        $values = AttributeValue::query()->with('translations')->whereIn('id', $valueIds)->get()->pluck('name')->all();

        return trim(implode(' - ', array_filter([$parent->name, ...$values])));
    }

    /**
     * @return array<int, int>
     */
    public static function valueIds(mixed $values): array
    {
        if (! is_array($values)) {
            $values = filled($values) ? [$values] : [];
        }

        return collect($values)->flatten()->filter(fn ($value) => filled($value))
            ->map(fn ($value) => (int) $value)->unique()->values()->all();
    }
}
