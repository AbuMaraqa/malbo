<?php

namespace Modules\Items\Http\Requests\Admin;

use Modules\Items\Repositories\Contracts\ProductRepositoryInterface;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Items\Entities\Addon;
use Modules\Items\Entities\AddonOption;
use Modules\Items\Entities\Attribute;
use Modules\Items\Entities\AttributeValue;
use Modules\Items\Enums\AttributeType;
use Modules\Items\Entities\Brand;
use Modules\Items\Entities\Category;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\ProductStatus;
use Modules\Items\Support\ProductVideo;
use Modules\Branch\Entities\Branch;

/**
 * The product form. The page sends everything but the files as one JSON field, `payload`: a large
 * form would otherwise go past PHP's max_input_vars, which silently drops the last fields, and
 * FormData cannot carry an empty list or a null. Files come as ordinary multipart fields under
 * the same paths (`image`, `gallery.0`, `variations.2.image`), so errors keep the form's paths.
 */
class ProductRequest extends FormRequest
{
    private const IMAGE = ['image', 'mimes:jpeg,jpg,png,gif,webp', 'max:5000'];

    private const MONEY_MAX = 'max:99999999.99'; // decimal(10, 2)

    private const SMALL_MAX = 'max:999999.99'; // decimal(8, 2)

    private const INT_MAX = 'max:2147483647';

    private const BOOLEANS = ['manage_stock', 'allow_backorder', 'is_featured', 'is_digital', 'is_new', 'is_best_seller'];

    /** What a variation row may set; anything else the browser adds is dropped. */
    private const VARIATION_KEYS = [
        'id', 'name', 'slug', 'short_description', 'description', 'sku', 'barcode', 'weight', 'is_featured', 'is_digital',
        'base_price', 'sale_price', 'cost_price', 'wholesale_price', 'discount_type', 'discount_value',
        'discount_start_date', 'discount_end_date', 'manage_stock', 'stock_quantity', 'stock_quantity_loaded', 'low_stock_threshold', 'stock_status',
        'allow_backorder', 'max_quantity_per_order', 'category_id', 'attribute_values', 'image', 'gallery',
        'removed_image', 'removed_gallery', 'gallery_order', 'meta_title', 'meta_description', 'meta_keywords',
        'fabric', 'composition', 'fit', 'care', 'model_height', 'model_weight', 'model_size',
    ];

    private const ADDON_KEYS = ['addon_id', 'is_required', 'min_selection', 'max_selection', 'max_quantity_per_option', 'allowed_options'];

    public function authorize(): bool
    {
        return true; // the route's `can` middleware decides
    }

    protected function prepareForValidation(): void
    {
        // A set or bundle is edited in its own form, under its own permission ("Bundle Update").
        if (filled($id = $this->route('product')) && app(ProductRepositoryInterface::class)->isBundle((int) $id)) {
            abort(404);
        }

        $data = $this->filled('payload')
            ? (array) json_decode((string) $this->input('payload'), true)
            : $this->input();

        unset($data['payload']);

        $data = self::emptyToNull($data);
        $data['slug'] = Str::slug((string) (($data['slug'] ?? null) ?: ($data['name'] ?? '')));

        // The form always sends these lists: one that is missing is an empty one.
        foreach (['tags', 'labels', 'category_id', 'branches', 'addons', 'variations'] as $list) {
            $data[$list] = (array) ($data[$list] ?? []);
        }

        $data = self::defaults($data);
        $data = self::knownColors($data);
        $data['variations'] = array_map(fn ($row) => is_array($row) ? self::defaults($row) : $row, $data['variations']);
        $data['addons'] = array_map(function ($row) {
            if (! is_array($row)) {
                return $row;
            }

            $row['is_required'] = (bool) ($row['is_required'] ?? false);
            $row['min_selection'] ??= 1;
            $row['max_quantity_per_option'] ??= 1;
            $row['allowed_options'] = (array) ($row['allowed_options'] ?? []);

            return $row;
        }, $data['addons']);

        $this->merge($data);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $id = (int) $this->route('product');
        $table = (new Product)->getTable();

        return [
            // Before the wildcards: the validator merges a wildcard into a rule already read, not the other way round.
            ...$this->allowedOptionRules(),
            ...$this->variationRowRules($id, $table),
            'name' => ['required', 'string', 'max:255'],
            // The column is unique over deleted rows too.
            'slug' => ['required', 'string', 'max:255', Rule::unique($table, 'slug')->ignore($id)],
            'sku' => ['nullable', 'string', 'max:255', Rule::unique($table, 'sku')->ignore($id)],
            'status' => ['required', Rule::in(array_keys(ProductStatus::formOptions()))],
            'tags' => ['array'],
            'tags.*' => ['required', 'string', 'max:255'],
            'labels' => ['array'],
            'labels.*' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'array', 'min:1'],
            'category_id.*' => ['integer', Rule::exists((new Category)->getTable(), 'id')],
            'brand_id' => ['nullable', 'integer', Rule::exists((new Brand)->getTable(), 'id')],
            'branches' => ['array'],
            'branches.*' => ['integer', Rule::exists((new Branch)->getTable(), 'id')],
            'image' => [$id ? 'nullable' : 'required', ...self::IMAGE],
            // The color each gallery image shows: saved images by media id, new ones in upload order.
            // Only COLOR values reach here (knownColors), anything else is "no color".
            'gallery_colors' => ['nullable', 'array'],
            'gallery_colors.*' => ['nullable', 'integer'],
            'gallery_new_colors' => ['nullable', 'array'],
            'gallery_new_colors.*' => ['nullable', 'integer'],
            'is_new' => ['boolean'],
            'is_best_seller' => ['boolean'],
            'video_url' => ['nullable', 'string', 'max:500', function (string $attribute, mixed $value, \Closure $fail): void {
                if (is_string($value) && ! ProductVideo::isValid($value)) {
                    $fail(__('Enter a YouTube or Vimeo link, or an https link to an .mp4 file.'));
                }
            }],
            ...$this->sharedRules(''),
            'addons' => ['array'],
            'addons.*.addon_id' => ['required', 'integer', 'distinct', Rule::exists((new Addon)->getTable(), 'id')->whereNull('deleted_at')],
            'addons.*.is_required' => ['boolean'],
            'addons.*.min_selection' => ['integer', 'min:0', self::INT_MAX],
            'addons.*.max_selection' => ['nullable', 'integer', 'min:1', self::INT_MAX, 'gte:addons.*.min_selection'],
            'addons.*.max_quantity_per_option' => ['integer', 'min:1', self::INT_MAX],
            'addons.*.allowed_options' => ['array'],
            'addons.*.allowed_options.*.addon_option_id' => ['required', 'integer', 'distinct'],
            'addons.*.allowed_options.*.price' => ['nullable', 'numeric', 'min:0', self::MONEY_MAX],
            'addons.*.allowed_options.*.is_default' => ['boolean'],
            'variations' => ['array'],
            'variations.*.sku' => ['nullable', 'string', 'max:255', 'distinct', 'different:sku'],
            'variations.*.name' => ['required', 'string', 'max:255'],
            'variations.*.slug' => ['nullable', 'string', 'max:255'],
            'variations.*.image' => ['nullable', ...self::IMAGE],
            'variations.*.category_id' => ['required', 'array', 'min:1'],
            'variations.*.category_id.*' => ['integer', Rule::exists((new Category)->getTable(), 'id')],
            'variations.*.attribute_values' => ['array'],
            'variations.*.attribute_values.*' => ['integer', Rule::exists((new AttributeValue)->getTable(), 'id')],
            ...$this->sharedRules('variations.*.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $names = [
            'name' => 'Name', 'slug' => 'Slug', 'sku' => 'Sku', 'barcode' => 'Barcode', 'weight' => 'Weight',
            'base_price' => 'Base Price', 'sale_price' => 'Sale Price', 'cost_price' => 'Cost Price', 'wholesale_price' => 'Wholesale Price',
            'discount_type' => 'Discount Type', 'discount_value' => 'Discount Amount',
            'discount_start_date' => 'Discount Start Date', 'discount_end_date' => 'Discount End Date',
            'stock_quantity' => 'Stock Quantity', 'low_stock_threshold' => 'Low Stock Threshold', 'stock_status' => 'Stock Status',
            'max_quantity_per_order' => 'Max Quantity Per Order', 'category_id' => 'Category', 'image' => 'Image', 'gallery' => 'Gallery',
            'meta_title' => 'Meta Title', 'meta_description' => 'Meta Description', 'meta_keywords' => 'Meta Keywords',
            'short_description' => 'Short Description', 'description' => 'Description',
            'fabric' => 'Fabric', 'composition' => 'Composition', 'fit' => 'Fit', 'care' => 'Care',
            'model_height' => 'Model height', 'model_weight' => 'Model weight', 'model_size' => 'Size the model wears',
        ];

        $attributes = ['video_url' => __('Video URL'), 'status' => __('Status'), 'brand_id' => __('Brand'), 'branches' => __('Branches'), 'tags' => __('Tags'), 'labels' => __('Labels')];

        foreach ($names as $field => $label) {
            $attributes[$field] = __($label);
            $attributes["variations.*.{$field}"] = __($label);
        }

        return [
            ...$attributes,
            'gallery_colors.*' => __('Color'),
            'gallery_new_colors.*' => __('Color'),
            'addons.*.addon_id' => __('Addon Group'),
            'addons.*.min_selection' => __('Min Selection'),
            'addons.*.max_selection' => __('Max Selection'),
            'addons.*.max_quantity_per_option' => __('Max Qty Per Option'),
            'addons.*.allowed_options.*.addon_option_id' => __('Option'),
            'addons.*.allowed_options.*.price' => __('Override Price'),
        ];
    }

    /**
     * A percentage over 100 has no meaning, for the product and for each variation.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $rows = ['' => $this->all(), ...collect((array) $this->input('variations', []))->mapWithKeys(fn ($row, $index) => ["variations.{$index}." => (array) $row])->all()];

            foreach ($rows as $prefix => $row) {
                if (($row['discount_type'] ?? null) === 'percentage' && is_numeric($row['discount_value'] ?? null) && $row['discount_value'] > 100) {
                    $validator->errors()->add("{$prefix}discount_value", __('A percentage discount cannot be over 100.'));
                }
            }
        }];
    }

    /**
     * What ProductService takes: lists in the form's order (validated() builds them in rule order,
     * and per-row rules come first) and only the fields a row may set.
     *
     * @return array<string, mixed>
     */
    public function forService(): array
    {
        $data = $this->validated();

        ksort($data['variations']);
        ksort($data['addons']);

        $data['variations'] = array_map(fn (array $row) => Arr::only($row, self::VARIATION_KEYS), array_values($data['variations']));
        $data['addons'] = array_map(function (array $row) {
            $row = Arr::only($row, self::ADDON_KEYS);
            ksort($row['allowed_options']);
            $row['allowed_options'] = array_values($row['allowed_options']);

            return $row;
        }, array_values($data['addons']));

        return $data;
    }

    /**
     * The fields a product and a variation have alike.
     *
     * @return array<string, mixed>
     */
    private function sharedRules(string $prefix): array
    {
        $money = ['nullable', 'numeric', 'min:0', self::MONEY_MAX];

        return [
            "{$prefix}short_description" => ['nullable', 'string'],
            "{$prefix}description" => ['nullable', 'string'],
            "{$prefix}barcode" => ['nullable', 'string', 'max:255'],
            "{$prefix}weight" => ['nullable', 'numeric', 'min:0', self::SMALL_MAX],
            "{$prefix}is_featured" => ['boolean'],
            "{$prefix}is_digital" => ['boolean'],
            "{$prefix}base_price" => ['required', 'numeric', 'min:0', self::MONEY_MAX],
            "{$prefix}sale_price" => $money,
            "{$prefix}cost_price" => $money,
            "{$prefix}wholesale_price" => $money,
            "{$prefix}discount_type" => ['nullable', Rule::in(['none', 'percentage', 'fixed'])],
            "{$prefix}discount_value" => ['nullable', 'numeric', 'min:0', self::SMALL_MAX],
            "{$prefix}discount_start_date" => ['nullable', 'date'],
            "{$prefix}discount_end_date" => ['nullable', 'date', "after_or_equal:{$prefix}discount_start_date"],
            "{$prefix}manage_stock" => ['boolean'],
            // Below 0 only when it is the stored value (a backorder product owing units), unchanged.
            "{$prefix}stock_quantity" => ['nullable', 'integer', self::INT_MAX, function (string $attribute, mixed $value, \Closure $fail): void {
                $loaded = $this->input(substr($attribute, 0, -strlen('stock_quantity')).'stock_quantity_loaded');

                if (is_numeric($value) && (int) $value < 0 && (string) (int) $value !== (string) $loaded) {
                    $fail('validation.min.numeric')->translate(['min' => 0]);
                }
            }],
            "{$prefix}stock_quantity_loaded" => ['nullable', 'integer'],
            "{$prefix}low_stock_threshold" => ['nullable', 'integer', 'min:0', self::INT_MAX],
            "{$prefix}stock_status" => ['nullable', Rule::in(['in_stock', 'out_of_stock', 'on_backorder'])],
            "{$prefix}allow_backorder" => ['boolean'],
            "{$prefix}max_quantity_per_order" => ['nullable', 'integer', 'min:1', self::INT_MAX],
            "{$prefix}gallery" => ['nullable', 'array'],
            "{$prefix}gallery.*" => self::IMAGE,
            "{$prefix}removed_image" => ['nullable', 'array'],
            "{$prefix}removed_image.*" => ['integer'],
            "{$prefix}removed_gallery" => ['nullable', 'array'],
            "{$prefix}removed_gallery.*" => ['integer'],
            "{$prefix}gallery_order" => ['nullable', 'array'],
            "{$prefix}gallery_order.*" => ['integer'],
            "{$prefix}meta_title" => ['nullable', 'string', 'max:60'],
            "{$prefix}meta_description" => ['nullable', 'string', 'max:160'],
            "{$prefix}meta_keywords" => ['nullable', 'string', 'max:255'],
            "{$prefix}fabric" => ['nullable', 'string', 'max:1000'],
            "{$prefix}composition" => ['nullable', 'string', 'max:1000'],
            "{$prefix}fit" => ['nullable', 'string', 'max:1000'],
            "{$prefix}care" => ['nullable', 'string', 'max:2000'],
            // The model in the photos: centimetres and kilograms.
            "{$prefix}model_height" => ['nullable', 'integer', 'min:50', 'max:250'],
            "{$prefix}model_weight" => ['nullable', 'integer', 'min:20', 'max:300'],
            "{$prefix}model_size" => ['nullable', 'string', 'max:32'],
        ];
    }

    /**
     * A gallery color that is not a value of a COLOR attribute (deleted since the page was opened, or
     * never one) is "no color": it must not stop the rest of the product from saving.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function knownColors(array $data): array
    {
        if (! is_array($data['gallery_colors'] ?? null) && ! is_array($data['gallery_new_colors'] ?? null)) {
            return $data;
        }

        $colors = AttributeValue::query()
            ->whereIn('attribute_id', Attribute::query()->where('type', AttributeType::COLOR->value)->select('id'))
            ->pluck('id')->map(fn ($id) => (int) $id)->all();

        foreach (['gallery_colors', 'gallery_new_colors'] as $key) {
            if (is_array($data[$key] ?? null)) {
                $data[$key] = array_map(
                    fn ($value) => is_numeric($value) && in_array((int) $value, $colors, true) ? (int) $value : null,
                    $data[$key],
                );
            }
        }

        return $data;
    }

    /**
     * An option must belong to its row's addon, and an addon lists it once (the table is unique on it).
     *
     * @return array<string, mixed>
     */
    private function allowedOptionRules(): array
    {
        $rules = [];
        $table = (new AddonOption)->getTable();

        foreach ((array) $this->input('addons', []) as $index => $addon) {
            $addonId = is_array($addon) && is_numeric($addon['addon_id'] ?? null) ? (int) $addon['addon_id'] : 0;

            foreach ((array) ($addon['allowed_options'] ?? []) as $position => $option) {
                $rules["addons.{$index}.allowed_options.{$position}.addon_option_id"] = [
                    Rule::exists($table, 'id')->where('addon_id', $addonId)->whereNull('deleted_at'),
                ];
            }
        }

        return $rules;
    }

    /**
     * A row's id must be a variation of this product, and its sku is unique apart from itself.
     *
     * @return array<string, mixed>
     */
    private function variationRowRules(int $productId, string $table): array
    {
        $rules = [];

        foreach ((array) $this->input('variations', []) as $index => $row) {
            $rowId = is_array($row) && is_numeric($row['id'] ?? null) ? (int) $row['id'] : null;

            $rules["variations.{$index}.id"] = ['nullable', 'integer', Rule::exists($table, 'id')->where('parent_id', $productId ?: -1)];
            $rules["variations.{$index}.sku"] = [Rule::unique($table, 'sku')->ignore($rowId)];
        }

        return $rules;
    }

    /**
     * Columns that are NOT NULL take a real value.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private static function defaults(array $row): array
    {
        foreach (self::BOOLEANS as $field) {
            if (array_key_exists($field, $row)) {
                $row[$field] = filter_var($row[$field], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $row[$field];
                $row[$field] ??= false;
            }
        }

        return $row;
    }

    /**
     * ConvertEmptyStringsToNull has already run when the JSON field is decoded.
     */
    private static function emptyToNull(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::emptyToNull($value);
            } elseif ($value === '') {
                $data[$key] = null;
            }
        }

        return $data;
    }
}
