<?php

namespace Modules\Store\Support;

use Illuminate\Support\Str;
use Modules\Items\Entities\Product;

/**
 * What a storefront product card shows.
 */
final class ProductCard
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $url,
        public readonly ?string $image,
        public readonly ?string $category,
        public readonly Price $price,
        public readonly ?string $summary = null,
    ) {}

    public static function from(Product $product, Price $price): self
    {
        return self::fromFacts(self::facts($product), $price);
    }

    /**
     * Everything of a card but its price and link, as plain values.
     *
     * @return array{id: int, slug: string, name: string, image: ?string, category: ?string, summary: ?string}
     */
    public static function facts(Product $product): array
    {
        $category = $product->relationLoaded('categories') ? $product->categories->first() : null;
        $summary = trim(strip_tags((string) $product->short_description));

        return [
            'id' => $product->getKey(),
            'slug' => $product->slug,
            // Fallback locale first; then any translation, so an English-only product still has a name.
            'name' => (string) ($product->name ?: $product->translations->first()?->name),
            'image' => $product->getFirstMediaUrl('product') ?: null,
            'category' => $category?->name,
            'summary' => $summary === '' ? null : Str::limit($summary, 180),
        ];
    }

    /**
     * @param  array{id: int, slug: string, name: string, image: ?string, category: ?string, summary: ?string}  $facts
     */
    public static function fromFacts(array $facts, Price $price): self
    {
        return new self(
            id: $facts['id'],
            name: $facts['name'],
            url: route('store.product', ['slug' => $facts['slug']]),
            image: $facts['image'],
            category: $facts['category'],
            price: $price,
            summary: $facts['summary'],
        );
    }

    /**
     * The card as a page prop.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'url' => $this->url,
            'image' => $this->image,
            'category' => $this->category,
            'summary' => $this->summary,
            'price' => $this->price->toArray(),
        ];
    }
}
