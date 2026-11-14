<?php

namespace Modules\Items\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Items\Entities\Category;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\BundleType;
use Modules\Items\Enums\ProductStatus;

/**
 * A set or bundle offer's form. A component is any product or variant that is not deleted and is not
 * itself a bundle.
 */
class BundleRequest extends FormRequest
{
    public const MAX_COMPONENTS = 10;

    private const IMAGE = ['image', 'mimes:jpeg,jpg,png,gif,webp', 'max:5000'];

    public function authorize(): bool
    {
        return true; // the route's `can` middleware decides
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $products = (new Product)->getTable();
        $id = $this->route('bundle');

        return [
            'bundle_type' => ['required', Rule::enum(BundleType::class)],
            'name' => ['required', 'string', 'max:255'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'slug' => ['nullable', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100', Rule::unique($products, 'sku')->ignore($id)],
            'status' => ['required', Rule::in(array_keys(ProductStatus::formOptions()))],
            'is_active' => ['boolean'],
            'is_new' => ['boolean'],
            'is_featured' => ['boolean'],
            'category_id' => ['nullable', 'array'],
            'category_id.*' => ['integer', Rule::exists((new Category)->getTable(), 'id')],
            'pricing' => ['required', Rule::in(['fixed', 'percent'])],
            'base_price' => ['nullable', 'required_if:pricing,fixed', 'numeric', 'min:0', 'max:99999999.99'],
            'sale_price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'bundle_discount_percent' => ['nullable', 'required_if:pricing,percent', 'numeric', 'min:1', 'max:90'],
            'max_quantity_per_order' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'components' => ['required', 'array', 'min:1', 'max:'.self::MAX_COMPONENTS],
            // A saved component's id: it is updated in place (BundleService::syncComponents).
            'components.*.id' => ['nullable', 'integer'],
            'components.*.product_id' => ['required', 'integer', Rule::exists($products, 'id')->whereNull('deleted_at')->whereNull('bundle_type')],
            'components.*.quantity' => ['required', 'integer', 'min:1', 'max:20'],
            'image' => ['nullable', ...self::IMAGE],
            'gallery' => ['nullable', 'array', 'max:20'],
            'gallery.*' => self::IMAGE,
            'removed_image' => ['nullable', 'array'],
            'removed_image.*' => ['integer'],
            'removed_gallery' => ['nullable', 'array'],
            'removed_gallery.*' => ['integer'],
            'gallery_order' => ['nullable', 'array'],
            'gallery_order.*' => ['integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'components.required' => __('Add at least one component.'),
            'components.*.product_id.exists' => __('Choose a product that is not deleted and is not a set or bundle itself.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'bundle_type' => __('Type'),
            'name' => __('Name'),
            'base_price' => __('Price'),
            'sale_price' => __('Sale price'),
            'bundle_discount_percent' => __('Discount %'),
            'components' => __('Components'),
            'components.*.product_id' => __('Product'),
            'components.*.quantity' => __('Quantity'),
            'category_id' => __('Category'),
        ];
    }
}
