<?php

namespace Modules\Store\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Items\Entities\Product;

/**
 * An Instagram post entered by hand: its picture (required on a new post), its https link, its
 * caption and the products it shows (at most 6, not variants).
 */
class InstagramPostRequest extends FormRequest
{
    public const MAX_PRODUCTS = 6;

    public function authorize(): bool
    {
        return true; // the route's `can` middleware decides
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'permalink' => ['required', 'string', 'max:500', 'url:https'],
            'caption' => ['nullable', 'string', 'max:2200'],
            'posted_at' => ['nullable', 'date'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'is_active' => ['boolean'],
            'products' => ['nullable', 'array', 'max:'.self::MAX_PRODUCTS],
            'products.*' => ['integer', 'distinct', Rule::exists((new Product)->getTable(), 'id')->whereNull('parent_id')->whereNull('deleted_at')],
            'image' => [$this->route('post') ? 'nullable' : 'required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5000', 'dimensions:max_width=6000,max_height=6000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'permalink' => __('Link to the post'),
            'caption' => __('Caption'),
            'posted_at' => __('Posted on'),
            'products' => __('Products in the post'),
            'image' => __('Picture'),
        ];
    }
}
