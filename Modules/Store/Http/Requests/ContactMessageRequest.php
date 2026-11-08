<?php

namespace Modules\Store\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Store\Services\ContactMessageService;

/**
 * A buyer's message to the store, from the storefront contact form or the app: a name, a phone
 * or an email to reply to, and the message.
 */
class ContactMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'required_without:email', 'string', 'max:40', 'regex:/^\+?[0-9\s\-()]{7,}$/'],
            'email' => ['nullable', 'required_without:phone', 'email', 'max:160'],
            'topic' => ['nullable', 'in:'.implode(',', ContactMessageService::TOPICS)],
            'message' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => __('Please write your name.'),
            'phone.required_without' => __('Leave a phone number or an email so we can reply.'),
            'email.required_without' => __('Leave a phone number or an email so we can reply.'),
            'phone.regex' => __('Please check the phone number.'),
            'email.email' => __('Please check the email address.'),
            'message.required' => __('Please write your message.'),
            'message.min' => __('Please write a little more so we can help.'),
            'message.max' => __('The message is too long.'),
            '*.max' => __('This is too long.'),
        ];
    }
}
