<?php

namespace Modules\Items\Services;

use Modules\Core\Support\RichText;
use Illuminate\Http\UploadedFile;
use Modules\Branch\Entities\Branch;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Core\Support\Translations;
use Modules\Items\Entities\BranchPrice;
use Modules\Items\Entities\Label;
use Modules\Items\Entities\Product;
use Modules\Items\Entities\Tag;
use Modules\Items\Enums\ProductActive;
use Modules\Items\Enums\ProductStatus;
use Modules\Items\Events\ProductUpdated;
use Modules\Items\Repositories\Contracts\ProductRepositoryInterface;
use Modules\Items\Support\ProductSeo;

/**
 * Writes products. $data is the product form's state (name, prices, tags, labels, category_id,
 * branches, addons, variations, image, gallery, meta inputs...); every database write of a save
 * runs in one transaction (image files written to disk are not removed if it rolls back),
 * translations are written for $locale only.
 */
class ProductService
{
    /** Form keys that are not product columns. */
    private const NOT_COLUMNS = [
        'tags', 'labels', 'category_id', 'categories', 'branches', 'addons', 'variations', 'attributes',
        'image', 'gallery', 'removed_image', 'removed_gallery', 'gallery_order',
        'locale', 'meta_title', 'meta_description', 'meta_keywords',
    ];

    public function __construct(
        private ProductRepositoryInterface $products,
        private ProductVariations $variations,
        private ProductAddons $addons,
    ) {}

    public function create(array $data, string $locale, int $userId): Product
    {
        $data = self::normalize($data);

        $product = DB::transaction(function () use ($data, $locale, $userId) {
            $attributes = [
                ...$this->columns($data),
                'meta_data' => ProductSeo::pack($data, $locale, null),
                'status' => $data['status'] ?? ProductStatus::PUBLISHED->value,
                'created_by' => $userId,
            ];

            /** @var Product $product */
            $product = $this->products->query()->getModel()->newInstance();
            $this->fillAndSave($product, $attributes, $data, $locale);
            $this->saveRelations($product, $data, $locale, $userId);

            if (! empty($data['variations'])) {
                $this->variations->sync($product, $data['variations'], $locale, $userId);
            }

            return $product;
        });

        event(new ProductUpdated());

        return $product;
    }

    public function update(Product $product, array $data, string $locale, int $userId): Product
    {
        $data = self::normalize($data);

        return DB::transaction(function () use ($product, $data, $locale, $userId) {
            $attributes = $this->columns($data);
            // "Keep empty" keeps the stored sku (NOT NULL); only a new product gets one generated.
            if (array_key_exists('sku', $attributes) && blank($attributes['sku'])) {
                unset($attributes['sku']);
            }
            $attributes['meta_data'] = ProductSeo::pack($data, $locale, $product->meta_data);

            $this->fillAndSave($product, $attributes, $data, $locale);

            if ($product->wasChanged('status')) {
                $product->variations()->update(['status' => $product->status]); // variations follow their parent
            }

            $this->saveRelations($product, $data, $locale, $userId);

            // An empty list removes them all; no `variations` key leaves them alone.
            if (array_key_exists('variations', $data)) {
                $this->variations->sync($product, $data['variations'] ?? [], $locale, $userId);
            }

            return $product;
        });
    }

    public function setStatus(Product $product, ProductStatus|int $status): void
    {
        DB::transaction(function () use ($product, $status) {
            $this->products->update($product, ['status' => $status]);
            $product->variations()->update(['status' => $product->status]); // variations follow their parent
        });
    }

    public function setActive(Product $product, bool $active): void
    {
        $this->products->update($product, [
            'is_active' => $active ? ProductActive::IS_ACTIVE->value : ProductActive::IN_ACTIVE->value,
        ]);
    }

    public function delete(Product $product): void
    {
        $this->products->delete($product);
    }

    /**
     * For good, with its variations: products.parent_id has no foreign key, so they would stay
     * behind holding their slug and sku. An order keeps its items (order_items.product_id is emptied).
     */
    public function forceDelete(Product $product): void
    {
        DB::transaction(function () use ($product): void {
            $product->variations()->withTrashed()->get()->each(fn (Product $variation) => $variation->forceDelete());
            $product->forceDelete();
        });
    }

    /**
     * The table's bulk delete, permanent like its single delete: a soft deleted product could not be
     * restored from anywhere and would keep its slug and sku taken.
     *
     * @param  iterable<int, Product>  $products
     */
    public function forceDeleteMany(iterable $products): void
    {
        DB::transaction(function () use ($products): void {
            foreach ($products as $product) {
                $this->forceDelete($product);
            }
        });
    }

    /**
     * @param  Collection<int, Product>  $products
     */
    public function deleteMany(Collection $products): void
    {
        DB::transaction(fn () => $products->each(fn (Product $product) => $this->products->delete($product)));
    }

    /**
     * @param  array<int|string, array<int|string, mixed>>  $prices  product or variation id => [branch id => price]; empty clears
     */
    public function saveBranchPrices(Product $product, array $prices): void
    {
        // Only this product, its variations and existing branches: $prices comes from the browser.
        $productIds = $product->variations()->pluck('id')->push($product->id)->map(fn ($id) => (int) $id)->all();
        $branchIds = Branch::query()->pluck('id')->map(fn ($id) => (int) $id)->all();

        DB::transaction(function () use ($prices, $productIds, $branchIds) {
            foreach ($prices as $productId => $branchPrices) {
                if (! in_array((int) $productId, $productIds, true)) {
                    continue;
                }

                foreach ($branchPrices as $branchId => $price) {
                    if (! in_array((int) $branchId, $branchIds, true)) {
                        continue;
                    }

                    $match = ['product_id' => $productId, 'branch_id' => $branchId];

                    if ($price === null || $price === '') {
                        BranchPrice::query()->where($match)->delete();
                    } else {
                        BranchPrice::query()->updateOrCreate($match, ['price' => $price]);
                    }
                }
            }
        });
    }

    /**
     * Saved images the form removed or reordered, then new uploads (the form's state also holds the
     * already stored files). Ids from the browser only ever reach this product's own media.
     */
    public static function attachMedia(Product $product, array $data): void
    {
        foreach (['product' => 'removed_image', 'product_gallery' => 'removed_gallery'] as $collection => $key) {
            if (! empty($data[$key])) {
                $product->getMedia($collection)->whereIn('id', array_map('intval', (array) $data[$key]))->each->delete();
                $product->unsetRelation('media');
            }
        }

        if (! empty($data['gallery_order'])) {
            $gallery = $product->getMedia('product_gallery')->modelKeys();
            $order = array_values(array_intersect(array_map('intval', (array) $data['gallery_order']), $gallery));

            if ($order !== []) {
                config('media-library.media_model')::setNewOrder($order);
                $product->unsetRelation('media');
            }
        }

        // A single file or the form's [uuid => file] state.
        $uploaded = fn ($files) => array_values(array_filter(
            is_array($files) ? $files : [$files],
            fn ($file) => $file instanceof UploadedFile,
        ));

        if ($image = $uploaded($data['image'] ?? null)[0] ?? null) {
            $product->addImage($image);
        }

        if ($gallery = $uploaded($data['gallery'] ?? null)) {
            $product->addGalleryImage($gallery);
        }
    }

    /**
     * Form values as columns expect them: '' is null, and the editor's HTML is made safe.
     */
    private static function normalize(array $data): array
    {
        $data = array_map(fn ($value) => $value === '' ? null : $value, $data);

        if (is_string($data['description'] ?? null)) {
            $data['description'] = RichText::make($data['description'])->toHtml();
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function columns(array $data): array
    {
        return Arr::except($data, [...self::NOT_COLUMNS, ...$this->products->query()->getModel()->translatedAttributes]);
    }

    private function fillAndSave(Product $product, array $attributes, array $data, string $locale): void
    {
        $translated = array_values(array_filter(
            $product->translatedAttributes,
            fn (string $field) => array_key_exists($field, $data),
        ));

        Translations::fill($product, Translations::fromForm($data, $locale, $translated));
        $product->fill($attributes)->save();
    }

    private function saveRelations(Product $product, array $data, string $locale, int $userId): void
    {
        if (isset($data['tags'])) {
            $product->tags()->sync($this->idsByName(Tag::class, $data['tags'], $locale));
        }
        if (isset($data['labels'])) {
            $product->labels()->sync($this->idsByName(Label::class, $data['labels'], $locale));
        }
        if (isset($data['category_id'])) {
            $product->categories()->sync($data['category_id']);
        }
        if (isset($data['branches'])) {
            $product->branches()->sync($data['branches']);
        }
        if (array_key_exists('addons', $data)) {
            $this->addons->replace($product, $data['addons'] ?? [], $userId);
        }

        self::attachMedia($product, $data);
    }

    /**
     * Tags and labels are chosen by name; a name that does not exist yet is created.
     *
     * @param  class-string<Tag|Label>  $class
     * @return array<int, int>
     */
    private function idsByName(string $class, mixed $names, string $locale): array
    {
        // As before the move: a single name (string) is accepted too.
        return collect(is_array($names) ? $names : [$names])->filter(fn ($name) => filled($name))->map(function (string $name) use ($class, $locale) {
            $model = $class::whereTranslation('name', $name)->first();

            if (! $model) {
                $model = new $class;
                Translations::fill($model, [$locale => ['name' => $name]]);
                $model->save();
            }

            return $model->id;
        })->unique()->values()->all();
    }
}
