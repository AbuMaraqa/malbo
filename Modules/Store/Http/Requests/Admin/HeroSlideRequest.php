<?php

namespace Modules\Store\Http\Requests\Admin;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A slide of the home page slider: its picture (required on a new slide; another for phones is
 * optional), its texts in the language being edited (all optional), where it leads (a path on the
 * site, like /ar/shop, or an https address), its order, its switch and optional dates.
 */
class HeroSlideRequest extends FormRequest
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
        $image = ['image', 'mimes:jpeg,jpg,png,webp', 'max:8000', 'dimensions:max_width=8000,max_height=8000'];

        return [
            'eyebrow' => ['nullable', 'string', 'max:120'],
            'title' => ['nullable', 'string', 'max:160'],
            'subtitle' => ['nullable', 'string', 'max:300'],
            'button_label' => ['nullable', 'string', 'max:60'],
            'link' => ['nullable', 'string', 'max:500', $this->linkRule()],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'is_active' => ['boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'image' => [$this->route('slide') ? 'nullable' : 'required', ...$image],
            'mobile_image' => ['nullable', ...$image],
            'remove_mobile_image' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'eyebrow' => __('Small heading'),
            'title' => __('Title'),
            'subtitle' => __('Subtitle'),
            'button_label' => __('Button text'),
            'link' => __('Link'),
            'starts_at' => __('Starts at'),
            'ends_at' => __('Ends at'),
            'image' => __('Picture'),
            'mobile_image' => __('Picture for phones'),
        ];
    }

    /**
     * A path on this site (/..., never //...) or an https address.
     */
    private function linkRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $value = trim((string) $value);
            $path = str_starts_with($value, '/') && ! str_starts_with($value, '//') && ! str_contains($value, '\\');
            $https = str_starts_with(strtolower($value), 'https://') && filter_var($value, FILTER_VALIDATE_URL) !== false;

            if (! $path && ! $https) {
                $fail(__('Enter a path on the store (starting with /) or an https:// address.'));
            }
        };
    }
}
