<?php

namespace Modules\Store\Http\Requests\Admin;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Modules\Store\Settings\MarketingSettings;

class MarketingSettingsRequest extends FormRequest
{
    /** Shortest and longest wait the admin may set, in minutes (a week). */
    public const MIN_MINUTES = 15;

    public const MAX_MINUTES = 10080;

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
            'pixel_enabled' => ['boolean'],
            'meta_pixel_id' => ['nullable', 'required_if_accepted:pixel_enabled', 'required_if_accepted:capi_enabled', 'string', 'regex:/^\d{5,20}$/'],
            'capi_enabled' => ['boolean'],
            'meta_capi_token' => ['nullable', 'string', 'max:1000', 'regex:/^\S+$/'],
            'forget_capi_token' => ['boolean'],
            'meta_test_event_code' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9]+$/'],
            'feed_token' => ['nullable', 'string', 'regex:/^[A-Za-z0-9]{16,64}$/'],
            'cart_reminders_enabled' => ['boolean'],
            'abandoned_after_minutes' => ['required', 'integer', 'min:'.self::MIN_MINUTES, 'max:'.self::MAX_MINUTES],
            'reminder_delays' => ['required', 'array', 'min:1', 'max:2'],
            'reminder_delays.*' => [
                'required', 'integer', 'min:'.self::MIN_MINUTES, 'max:'.self::MAX_MINUTES,
                // Each reminder comes after the one before it.
                function (string $attribute, mixed $value, Closure $fail): void {
                    $index = (int) substr($attribute, strrpos($attribute, '.') + 1);
                    $previous = $this->input('reminder_delays.'.($index - 1));

                    if ($index > 0 && is_numeric($previous) && (int) $value <= (int) $previous) {
                        $fail(__('Each reminder must come after the one before it.'));
                    }
                },
            ],
        ];
    }

    /**
     * Switching the server events on needs a token: typed now, or one saved before (and kept).
     *
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $saved = filled(app(MarketingSettings::class)->meta_capi_token) && ! $this->boolean('forget_capi_token');

            if ($this->boolean('capi_enabled') && blank($this->input('meta_capi_token')) && ! $saved) {
                $validator->errors()->add('meta_capi_token', __('Enter the Conversions API token.'));
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'meta_pixel_id' => __('Pixel ID'),
            'meta_capi_token' => __('Conversions API token'),
            'meta_test_event_code' => __('Test event code'),
            'feed_token' => __('Feed token'),
            'abandoned_after_minutes' => __('Abandoned after'),
            'reminder_delays' => __('Reminders'),
            'reminder_delays.*' => __('Reminder delay'),
        ];
    }
}
