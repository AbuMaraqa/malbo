<?php

namespace Modules\Items\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Items\Entities\Product;

/**
 * "Complete the look": the products shown next to a product, in order. Other products only (never
 * itself, a variant or a set), at most MAX.
 */
class LookRequest extends FormRequest
{
    public const MAX = 8;

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

        return [
            'products' => ['present', 'array', 'max:'.self::MAX],
            'products.*' => [
                'integer',
                'distinct',
                Rule::notIn([(int) $this->route('product')]),
                Rule::exists($products, 'id')->whereNull('parent_id')->whereNull('bundle_type')->whereNull('deleted_at'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'products.*.not_in' => __('A product cannot complete its own look.'),
            'products.*.exists' => __('Choose a product: not a variant, a set or a deleted product.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['products' => __('Complete the look')];
    }
}
