<?php

namespace Modules\Store\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PageRequest extends FormRequest
{
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
            'title' => ['required', 'string', 'max:160'],
            'body' => ['nullable', 'string'],
            'meta_description' => ['nullable', 'string', 'max:300'],
            'slug' => ['nullable', 'string', 'max:120'],
            'is_published' => ['boolean'],
            'show_in_footer' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => __('Title is required'),
            'title.max' => __('Title must not exceed 160 characters'),
            'slug.max' => __('Link must not exceed 120 characters'),
            'meta_description.max' => __('Description must not exceed 300 characters'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => __('Title'),
            'body' => __('Page text'),
            'meta_description' => __('Search engine description'),
            'slug' => __('Link'),
            'sort_order' => __('Sort Order'),
        ];
    }
}
