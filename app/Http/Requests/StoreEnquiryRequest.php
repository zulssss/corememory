<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A real Form Request, on a plain HTML POST.
 *
 * The booking wizard is Livewire and validates through its own rules() against
 * the shared BookingRules class. The contact form has no such constraint, so
 * it uses the framework's intended mechanism — which also means the page works
 * with JavaScript disabled.
 *
 * The honeypot is deliberately NOT a rule here. Validating it would bounce the
 * bot back with an error naming the field it got wrong, which is free tuition.
 * The controller checks it instead and returns the ordinary success response,
 * so a bot learns nothing and stops trying.
 */
class StoreEnquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    /** Judge the digits, not the punctuation — see App\Support\Phone. */
    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $this->merge(['phone' => Phone::format($this->input('phone'))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'email' => ['required', 'email:rfc', 'max:160'],

            // Optional here — someone asking a general question should not be
            // forced to hand over a phone number.
            'phone' => ['nullable', 'string', 'max:32', 'regex:'.BookingRules::PHONE_REGEX],

            'message' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'phone.regex' => __('booking.errors.phone'),
            'message.min' => __('contact.errors.message_short'),
        ];
    }
}
