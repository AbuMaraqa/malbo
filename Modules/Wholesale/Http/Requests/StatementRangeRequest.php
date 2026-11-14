<?php

namespace Modules\Wholesale\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Wholesale\Services\MerchantStatement;

/**
 * A statement's date range (both optional; none: MerchantStatement::defaultRange()).
 */
class StatementRangeRequest extends FormRequest
{
    public const MAX_YEARS = 2;

    public function authorize(): bool
    {
        return true; // the routes' middleware decides
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    /**
     * At most two years in one statement.
     *
     * @return list<callable(\Illuminate\Validation\Validator): void>
     */
    public function after(): array
    {
        return [function (\Illuminate\Validation\Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $range = $this->range();

            if (\Illuminate\Support\Carbon::parse($range['from'])->addYears(self::MAX_YEARS)->lt(\Illuminate\Support\Carbon::parse($range['to']))) {
                $validator->errors()->add('from', __('A statement covers at most :years years: choose a later start.', ['years' => self::MAX_YEARS]));
            }
        }];
    }

    /**
     * @return array{from: string, to: string}
     */
    public function range(): array
    {
        $default = MerchantStatement::defaultRange();

        return [
            'from' => $this->validated('from') ?: $default['from'],
            'to' => $this->validated('to') ?: $default['to'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['from' => __('From'), 'to' => __('To')];
    }
}
