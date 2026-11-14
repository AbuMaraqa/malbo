<?php

namespace Modules\Store\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Store\Entities\Review;

/**
 * A buyer's review: 1 to 5 stars, a short title, the text (plain, at most 2000 characters) and up to
 * four photos (JPG, PNG or WebP by their content, 5 MB each).
 */
class ReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'between:1,5'],
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['required', 'string', 'min:3', 'max:2000'],
            'photos' => ['nullable', 'array', 'max:'.Review::MAX_PHOTOS],
            // mimetypes reads the file's content, not its name.
            'photos.*' => ['file', 'max:5120', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'dimensions:max_width=6000,max_height=6000'],
        ];
    }

    public function attributes(): array
    {
        return ['photos.*' => __('photo')];
    }
}
