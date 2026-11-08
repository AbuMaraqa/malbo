<?php

namespace Modules\Items\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Items\Entities\Label;
use Modules\Items\Http\Requests\Admin\Concerns\ColorRule;
use Modules\Items\Http\Requests\Admin\Concerns\NormalizesSlug;

class LabelRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique((new Label)->getTable(), 'slug')->ignore($this->route('label'))],
            'description' => ['nullable', 'string'],
            'text_color' => ['required', 'string', ColorRule::HEX],
            'background_color' => ['required', 'string', ColorRule::HEX],
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
            'description' => __('Description'),
            'text_color' => __('Text Color'),
            'background_color' => __('Background Color'),
        ];
    }
}
