<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Malaysian mobile numbers in one canonical form: 012-452 2344.
 *
 * Every phone number entering the system passes through here — the booking
 * wizard, the contact form, CreateBooking — so the studio sees one format in
 * the admin, and so validation judges the DIGITS rather than the punctuation.
 *
 * That second point is the real reason this exists. A correctly typed
 * "012-452 2344" was being rejected because of characters nobody could see: a
 * trailing space, or the non-breaking hyphen (U+2011) that macOS and iOS smart
 * punctuation and autofill substitute for a normal one. Stripping to digits
 * first makes all of those the same number.
 */
final class Phone
{
    /**
     * The canonical form if this is a Malaysian mobile number; otherwise the
     * trimmed input, unchanged, so validation can still reject it with a
     * message the couple recognises. Blank becomes null.
     */
    public static function format(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value) ?? '';

        // International form (+60 / 60) to local: 60 12… → 012…
        if (str_starts_with($digits, '60')) {
            $digits = '0'.substr($digits, 2);
        }

        // 01X plus seven digits (10 total), or plus eight (11 total — 011 and
        // the newer ranges).
        if (! preg_match('/^01\d{8,9}$/', $digits)) {
            return trim($value);
        }

        return strlen($digits) === 11
            ? substr($digits, 0, 3).'-'.substr($digits, 3, 4).' '.substr($digits, 7)
            : substr($digits, 0, 3).'-'.substr($digits, 3, 3).' '.substr($digits, 6);
    }
}
