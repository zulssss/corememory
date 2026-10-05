<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\EnquirySource;
use App\Enums\SessionSlot;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * The validation rules for a booking, in one place.
 *
 * The brief asks for Form Requests. The public wizard is a Livewire component,
 * which validates through its own rules() method rather than a FormRequest —
 * so the rules live here and both consume them. Keeping them in a single class
 * is the part that actually matters: there is exactly one definition of what a
 * valid booking looks like, and no chance of an HTTP path and a Livewire path
 * disagreeing.
 *
 * StoreBookingRequest (a real FormRequest) also uses these, so an API or a
 * no-JS fallback form validates identically.
 */
class BookingRules
{
    /**
     * Malaysian mobile numbers, accepted loosely.
     *
     * All of these are the same number and all must pass:
     *   012-345 6789 · 0123456789 · +60 12-345 6789 · +60123456789 · 60123456789
     *
     * Deliberately permissive: a couple rejected for putting a space in their
     * phone number is a couple the studio never hears from.
     */
    public const PHONE_REGEX = '/^(\+?60|0)\s?1\d([\s-]?\d){7,8}$/';

    /** @return array<string, mixed> */
    public static function dates(): array
    {
        return [
            'dates' => ['required', 'array', 'min:1', 'max:4'],
            'dates.*.event_date' => ['required', 'date'],
            'dates.*.session_slot' => ['required', new Enum(SessionSlot::class)],
            'dates.*.label' => ['nullable', 'string', 'max:60'],
            // Required so the studio can price travel and plan the day before
            // anyone picks up the phone. Asked for as a plain warning on
            // Continue, never as an asterisk.
            'dates.*.venue' => ['required', 'string', 'max:160'],
            'dates.*.city' => ['required', 'string', 'max:80'],
            'dates.*.state' => ['required', 'string', 'max:80'],
        ];
    }

    /** @return array<string, mixed> */
    public static function package(): array
    {
        return [
            'packageId' => ['required', 'integer', Rule::exists('packages', 'id')->where('is_active', true)],
        ];
    }

    /** @return array<string, mixed> */
    public static function addOns(): array
    {
        return [
            'addOns' => ['array'],
            'addOns.*' => ['integer', 'min:0', 'max:99'],
        ];
    }

    /** @return array<string, mixed> */
    public static function details(): array
    {
        return [
            'partnerOneName' => ['required', 'string', 'min:2', 'max:80'],
            'partnerTwoName' => ['nullable', 'string', 'max:80'],
            'email' => ['required', 'email:rfc', 'max:160'],
            'phone' => ['required', 'string', 'max:32', 'regex:'.self::PHONE_REGEX],
            'guestCount' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'source' => ['nullable', new Enum(EnquirySource::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, mixed> */
    public static function review(): array
    {
        return [
            'terms' => ['accepted'],
        ];
    }

    /**
     * The honeypot. A real person never sees this field, so it must stay
     * empty; a bot fills every input it finds. No CAPTCHA, as the brief asks.
     */
    public static function honeypot(): array
    {
        return [config('booking.honeypot_field') => ['nullable', 'size:0'],
        ];
    }

    /** @return array<string, mixed> */
    public static function all(): array
    {
        return [
            ...self::dates(),
            ...self::package(),
            ...self::addOns(),
            ...self::details(),
            ...self::review(),
            ...self::honeypot(),
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'dates.required' => __('booking.errors.no_dates'),
            'dates.min' => __('booking.errors.no_dates'),
            'dates.*.event_date.required' => __('booking.errors.date_required'),
            'dates.*.session_slot.required' => __('booking.errors.session_required'),
            'dates.*.venue.required' => __('booking.errors.venue_required'),
            'dates.*.city.required' => __('booking.errors.city_required'),
            'dates.*.state.required' => __('booking.errors.state_required'),
            'packageId.required' => __('booking.errors.package_required'),
            'phone.regex' => __('booking.errors.phone'),
            'terms.accepted' => __('booking.errors.terms'),
        ];
    }

    /** @return array<string, string> */
    public static function attributes(): array
    {
        return [
            'partnerOneName' => __('booking.fields.partner_one'),
            'partnerTwoName' => __('booking.fields.partner_two'),
            'guestCount' => __('booking.fields.guest_count'),
            'packageId' => __('booking.fields.package'),
        ];
    }
}
