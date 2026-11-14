<?php

namespace Modules\Store\Support;

use Illuminate\Support\Str;
use Modules\Items\Entities\Product;
use Modules\Store\Services\ProductBadges;

/**
 * What a storefront product card shows. Its badges are decided by ProductBadges from the card's
 * facts and its price.
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
        /** @var list<array<string, mixed>> */
        public readonly array $badges = [],
        /** @var list<array{name: string, available: bool}> */
        public readonly array $sizes = [],
    ) {}

    public static function from(Product $product, Price $price, ?int $sizeAttributeId = null): self
    {
        return self::fromFacts(self::facts($product, $sizeAttributeId), $price);
    }

    /**
     * Everything of a card but its price and link, as plain values (`badges` are ProductBadges::facts,
     * `sizes` CardSizes::facts for the size attribute).
     *
     * @return array{id: int, slug: string, name: string, image: ?string, category: ?string, summary: ?string, badges: array<string, mixed>, sizes: list<array{name: string, available: bool}>}
     */
    public static function facts(Product $product, ?int $sizeAttributeId = null): array
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
            'badges' => ProductBadges::facts($product),
            'sizes' => CardSizes::facts($product, $sizeAttributeId),
        ];
    }

    /**
     * @param  array{id: int, slug: string, name: string, image: ?string, category: ?string, summary: ?string}  $facts
     * @param  list<array<string, mixed>>  $badges  from ProductBadges::for()
     */
    public static function fromFacts(array $facts, Price $price, array $badges = []): self
    {
        return new self(
            id: $facts['id'],
            name: $facts['name'],
            url: route('store.product', ['slug' => $facts['slug']]),
            image: $facts['image'],
            category: $facts['category'],
            price: $price,
            summary: $facts['summary'],
            badges: $badges,
            // Rows cached before sizes were part of a card have none.
            sizes: $facts['sizes'] ?? [],
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
            'badges' => $this->badges,
            'sizes' => $this->sizes,
        ];
    }
}
