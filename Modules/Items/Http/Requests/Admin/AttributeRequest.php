<?php

namespace Modules\Items\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Items\Entities\Attribute;
use Modules\Items\Entities\AttributeValue;
use Modules\Items\Enums\AttributeType;
use Modules\Items\Http\Requests\Admin\Concerns\ColorRule;
use Modules\Items\Http\Requests\Admin\Concerns\NormalizesSlug;

class AttributeRequest extends FormRequest
{
    use NormalizesSlug;

    public function authorize(): bool
    {
        return true; // the route's `can` middleware decides
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $id = $this->route('attribute');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique((new Attribute)->getTable(), 'slug')->ignore($id)],
            'type' => ['required', Rule::enum(AttributeType::class)],
            'description' => ['nullable', 'string'],
            'values' => ['nullable', 'array'],
            // A value of another attribute cannot be taken over by sending its id.
            'values.*.id' => ['nullable', 'integer', Rule::exists((new AttributeValue)->getTable(), 'id')->where('attribute_id', (int) $id)],
            'values.*.name' => ['required', 'string', 'max:255'],
            'values.*.description' => ['nullable', 'string', 'max:255'], // the column is varchar(255)
            'values.*.color_code' => ['nullable', 'string', ColorRule::HEX],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('Name'),
            'slug' => __('Slug'),
            'type' => __('Attribute Type'),
            'description' => __('Description'),
            'values.*.name' => __('Name'),
            'values.*.description' => __('Description'),
            'values.*.color_code' => __('Color'),
        ];
    }
}
