<?php

namespace Modules\Wholesale\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Wholesale\Services\CatalogPdfService;

/**
 * A PDF catalog to make: a category (its slug; empty: all) and which prices it shows (`retail`, the
 * default, or `wholesale`: the merchant's own prices).
 */
class CatalogPdfRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // `merchant.approved` decides
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category' => ['nullable', 'string', 'max:200'],
            'prices' => ['nullable', Rule::in(CatalogPdfService::PRICES)],
        ];
    }

    public function category(): ?string
    {
        return filled($this->validated('category')) ? (string) $this->validated('category') : null;
    }

    public function prices(): string
    {
        return $this->validated('prices') ?: CatalogPdfService::RETAIL;
    }
}
