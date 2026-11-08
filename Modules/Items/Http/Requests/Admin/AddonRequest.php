<?php

namespace Modules\Items\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Items\Entities\AddonOption;
use Modules\Items\Enums\AddonType;
use Modules\Items\Repositories\Contracts\AddonRepositoryInterface;

class AddonRequest extends FormRequest
{
    private const IMAGE = ['image', 'mimes:jpeg,jpg,png,gif,webp', 'max:10000'];

    public function authorize(): bool
    {
        return true; // the route's `can` middleware decides
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $id = (int) $this->route('addon');

        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(AddonType::class)],
            'description' => ['nullable', 'string'],
            'options' => ['nullable', 'array'],
            // An option of another addon cannot be taken over by sending its id.
            'options.*.id' => ['nullable', 'integer', Rule::exists((new AddonOption)->getTable(), 'id')->where('addon_id', $id)->whereNull('deleted_at')],
            'options.*.name' => ['required', 'string', 'max:255'],
            'options.*.price' => ['required', 'numeric', 'min:0', 'max:99999999.99'], // decimal(10, 2)
            'options.*.is_default' => ['boolean'],
            'options.*.is_quantifiable' => ['boolean'],
            'options.*.description' => ['nullable', 'string'],
            'options.*.image' => ['nullable', ...self::IMAGE],
            'options.*.removed_image' => ['nullable', 'array'],
            ...$this->requiredImages($id),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('Name'),
            'type' => __('Type'),
            'description' => __('Description'),
            'options.*.name' => __('Name'),
            'options.*.price' => __('Price'),
            'options.*.description' => __('Description'),
            'options.*.image' => __('Image'),
        ];
    }

    /**
     * Each option needs an image: a new one always, a saved one when it has none or its image was removed.
     *
     * @return array<string, list<string>>
     */
    private function requiredImages(int $id): array
    {
        $saved = $id ? app(AddonRepositoryInterface::class)->findForForm($id)->addonOptions->keyBy('id') : collect();
        $rules = [];

        foreach ((array) $this->input('options', []) as $index => $option) {
            $option = (array) $option;
            $hasImage = $saved->get($option['id'] ?? null)?->hasMedia('addon_option') && empty($option['removed_image']);

            if (! $hasImage) {
                $rules["options.{$index}.image"] = ['required', ...self::IMAGE];
            }
        }

        return $rules;
    }
}
