<?php

declare(strict_types=1);

/*
|------------------------------------------------------------------------------
| Booking & Availability Rules
|------------------------------------------------------------------------------
| Rules that govern what the calendar allows and when a slot is actually held.
|
| These are STRUCTURAL settings — they change how the availability engine
| behaves, so they live in config and are changed by a developer. Things the
| studio owner edits day to day (deposit %, copy, contact details) live in the
| database `settings` table and are edited in the admin panel instead.
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Blocking statuses
    |--------------------------------------------------------------------------
    | A booking in one of these statuses writes rows into `slot_holds` and so
    | physically takes the date off the calendar. Backed by a unique index, so
    | two of them can never occupy the same slot — not even on a simultaneous
    | submit.
    |
    | Adding 'pending' here makes an enquiry hold its slot immediately. Nothing
    | else needs to change: holds are written wherever the status qualifies.
    */
    'blocking_statuses' => [
        'confirmed',
        'deposit_paid',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tentative statuses
    |--------------------------------------------------------------------------
    | Shown as "another enquiry is pending for this slot" but NOT held. More
    | than one couple may enquire for the same slot — that is the point of a
    | soft hold, and the studio decides between them.
    */
    'tentative_statuses' => [
        'pending',
    ],

    /*
    |--------------------------------------------------------------------------
    | Booking window
    |--------------------------------------------------------------------------
    | How soon and how far ahead a couple may book. Dates outside the window
    | are greyed out with a reason rather than silently missing.
    */
    'min_lead_days' => (int) env('BOOKING_MIN_LEAD_DAYS', 14),
    'max_ahead_months' => (int) env('BOOKING_MAX_AHEAD_MONTHS', 24),

    /*
    |--------------------------------------------------------------------------
    | Pending auto-lapse
    |--------------------------------------------------------------------------
    | A pending enquiry nobody actions is cancelled after this many days, so a
    | popular date doesn't carry a stale "tentative" notice forever. Run by the
    | bookings:expire-stale command on the scheduler. The studio can revive one.
    */
    'pending_lapse_days' => (int) env('BOOKING_PENDING_LAPSE_DAYS', 14),

    /*
    |--------------------------------------------------------------------------
    | Deposit
    |--------------------------------------------------------------------------
    | Default only. The owner overrides this in admin settings; this value is
    | the fallback when no setting has been saved yet.
    */
    'default_deposit_percent' => (float) env('BOOKING_DEPOSIT_PERCENT', 30),

    /*
    |--------------------------------------------------------------------------
    | References & invoice numbers
    |--------------------------------------------------------------------------
    | Booking reference: CM-2026-0001. Invoice number: CM-INV-2026-0001.
    | Both reset their counter each calendar year and are never reused.
    */
    'reference_prefix' => env('BOOKING_REFERENCE_PREFIX', 'CM'),
    'invoice_prefix' => env('INVOICE_NUMBER_PREFIX', 'CM'),
    'sequence_padding' => 4,

    /*
    |--------------------------------------------------------------------------
    | Slot clock times
    |--------------------------------------------------------------------------
    | Displayed on the calendar step so a couple knows what they're choosing.
    | Descriptive only — the engine treats a slot as an indivisible unit and
    | does no duration arithmetic.
    */
    'slot_times' => [
        'morning' => '9:00 AM – 1:00 PM',
        'afternoon' => '2:00 PM – 6:00 PM',
        'evening' => '7:00 PM – 11:00 PM',
        'full_day' => '',
    ],

    /*
    |--------------------------------------------------------------------------
    | Anti-spam
    |--------------------------------------------------------------------------
    | Honeypot plus rate limiting, no CAPTCHA. The honeypot field is named to
    | look plausible to a bot but is hidden from real users and must stay empty.
    */
    'honeypot_field' => 'website_url',
    'rate_limit_per_hour' => 5,

];
