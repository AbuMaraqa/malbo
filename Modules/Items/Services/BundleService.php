<?php

namespace Modules\Items\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Modules\Core\Support\RichText;
use Modules\Core\Support\Translations;
use Modules\Items\Entities\BundleComponent;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\ProductActive;
use Modules\Items\Repositories\Contracts\BundleRepositoryInterface;
use Modules\Items\Repositories\Contracts\ProductRepositoryInterface;
use Modules\Items\Support\BundleStock;

/**
 * Writes sets and bundle offers: a product row (`bundle_type`) with its texts in one locale, images,
 * categories, price and components in order.
 *
 * Its own stock is inert and never written again: managed, 0, marked out of stock. Any code that
 * reads it as an ordinary product sees "out of stock", and InventoryService refuses to sell it.
 *
 * Price: `fixed` keeps the base and sale price typed; `percent` sets the base price to the regular
 * prices of the components times their quantities (BundleStock::regularPrice) and the percentage as
 * the product's own discount, so PricingService shows the saving. A component's price change brings
 * the percent bundles holding it up to date (refreshPrices).
 */
class BundleService
{
    /** Columns the form writes. */
    private const COLUMNS = ['bundle_type', 'slug', 'sku', 'status', 'is_active', 'is_new', 'is_featured', 'max_quantity_per_order'];

    private const TEXTS = ['name', 'short_description', 'description'];

    public function __construct(
        private readonly ProductRepositoryInterface $products,
        private readonly BundleRepositoryInterface $bundles,
    ) {}

    /**
     * @param  array<string, mixed>  $data  BundleRequest's fields
     */
    public function create(array $data, string $locale, int $userId): Product
    {
        return DB::transaction(function () use ($data, $locale, $userId): Product {
            /** @var Product $bundle */
            $bundle = $this->products->query()->getModel()->newInstance();
            $bundle->forceFill([
                // Inert stock: the components hold it.
                'manage_stock' => true,
                'allow_backorder' => false,
                'stock_status' => 'out_of_stock',
                'created_by' => $userId,
            ]);

            return $this->save($bundle, $data, $locale);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Product $bundle, array $data, string $locale): Product
    {
        return DB::transaction(fn () => $this->save($bundle, $data, $locale));
    }

    /**
     * For good, as products are: order lines keep their snapshot (their link is emptied).
     */
    public function delete(Product $bundle): void
    {
        DB::transaction(fn () => $bundle->forceDelete());
    }

    /**
     * Percent bundles holding any of these products (or their parents) take the new regular prices.
     *
     * @param  list<int>  $productIds
     */
    public function refreshPrices(array $productIds): void
    {
        foreach ($this->bundles->containing($productIds, percentOnly: true) as $bundleId) {
            $bundle = $this->bundles->withComponents($bundleId);

            if ($bundle !== null && $bundle->bundle_discount_percent !== null) {
                $base = $this->componentsTotal($bundle);

                if (round((float) $bundle->base_price, 2) !== $base) {
                    $bundle->forceFill(['base_price' => $base])->save();
                }
            }
        }
    }

    /**
     * A value hidden or shown hides or shows the variants that hold it: the percent bundles of those
     * variants' products take the new regular prices.
     */
    public function refreshForValue(int $valueId): void
    {
        $this->refreshPrices($this->bundles->variantsWithValue($valueId));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function save(Product $bundle, array $data, string $locale): Product
    {
        $texts = Arr::only($data, self::TEXTS);
        if (is_string($texts['description'] ?? null)) {
            $texts['description'] = RichText::make($texts['description'])->toHtml();
        }

        Translations::fill($bundle, Translations::fromForm($texts, $locale, array_keys($texts)));

        $columns = Arr::only($data, self::COLUMNS);
        $columns['slug'] = $this->products->uniqueSlug(filled($data['slug'] ?? null) ? (string) $data['slug'] : (string) $data['name'], $bundle->exists ? $bundle->id : null);
        $columns['is_active'] = ! empty($data['is_active']) ? ProductActive::IS_ACTIVE->value : ProductActive::IN_ACTIVE->value;
        $columns['is_new'] = ! empty($data['is_new']);
        $columns['is_featured'] = ! empty($data['is_featured']);

        if (blank($columns['sku'] ?? null)) {
            unset($columns['sku']); // a new bundle gets one generated; a saved one keeps its own
        }

        $percent = ($data['pricing'] ?? 'fixed') === 'percent' ? round((float) $data['bundle_discount_percent'], 2) : null;
        $bundle->fill($columns);
        $bundle->forceFill([
            'bundle_discount_percent' => $percent,
            ...($percent === null ? [
                'base_price' => round((float) $data['base_price'], 2),
                'sale_price' => filled($data['sale_price'] ?? null) ? round((float) $data['sale_price'], 2) : null,
                'discount_type' => 'none',
                'discount_value' => null,
            ] : [
                'base_price' => (float) ($bundle->base_price ?? 0),
                'sale_price' => null,
                'discount_type' => 'percentage',
                'discount_value' => $percent,
            ]),
            'discount_start_date' => null,
            'discount_end_date' => null,
        ])->save();

        $bundle->categories()->sync(array_map('intval', (array) ($data['category_id'] ?? [])));
        $this->syncComponents($bundle, (array) $data['components']);

        if ($percent !== null) {
            $bundle->unsetRelation('bundleComponents');
            $bundle->load(BundleStock::relations());
            $bundle->forceFill(['base_price' => $this->componentsTotal($bundle)])->save();
        }

        ProductService::attachMedia($bundle, $data);

        return $bundle;
    }

    /**
     * The components in the form's order. A row sent with the id of one of this bundle's components
     * updates it in place: carts hold the buyer's choices by component id, so an edit (a new name, a
     * price, the order) never breaks them. Rows without a known id are added; components left out
     * are removed, and only the cart lines that chose for them stop fitting the bundle.
     *
     * @param  list<array{id?: int|string|null, product_id: int|string, quantity: int|string}>  $rows
     */
    private function syncComponents(Product $bundle, array $rows): void
    {
        $saved = $bundle->bundleComponents()->get()->keyBy('id');
        $kept = [];

        foreach (array_values($rows) as $index => $row) {
            $attributes = ['product_id' => (int) $row['product_id'], 'quantity' => (int) $row['quantity'], 'sort_order' => $index];
            /** @var BundleComponent|null $component */
            $component = $saved->get((int) ($row['id'] ?? 0));

            if ($component !== null && ! in_array($component->id, $kept, true)) {
                $component->update($attributes);
            } else {
                $component = $bundle->bundleComponents()->save(new BundleComponent($attributes));
            }

            $kept[] = $component->id;
        }

        $saved->except($kept)->each(fn (BundleComponent $component) => $component->delete());
    }

    private function componentsTotal(Product $bundle): float
    {
        return round($bundle->bundleComponents->sum(fn (BundleComponent $component) => BundleStock::regularPrice($component) * (int) $component->quantity), 2);
    }
}
