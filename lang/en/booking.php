<?php

declare(strict_types=1);

/*
| Booking wizard, availability and status strings.
*/

return [

    'slots' => [
        'morning' => 'Morning',
        'afternoon' => 'Afternoon',
        'evening' => 'Evening',
        'full_day' => 'Full day',
    ],

    'statuses' => [
        'pending' => 'Pending',
        'contacted' => 'Contacted',
        'quoted' => 'Quoted',
        'confirmed' => 'Confirmed',
        'deposit_paid' => 'Deposit paid',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ],

    /*
    | REQUIRED COPY — shown at the calendar step, on the review step, and in
    | the confirmation email. A free date is not a guarantee.
    |
    | Styled as a micro-label note, never as a red error box: unmissable, but
    | not alarming.
    */
    'availability_disclaimer' => 'Dates shown as available are subject to confirmation. Our team will verify availability with you before your booking is confirmed.',

    'not_a_confirmation' => 'This is a booking request, not a confirmation. We will contact you to confirm your date.',

    'availability' => [
        'available' => 'Available',
        'blocked' => 'Not available',
        'tentative' => 'Tentative',
        'tentative_notice' => 'Another enquiry is pending for this slot.',
        'reason_booked' => 'Already booked.',
        'reason_blocked' => 'Unavailable.',
        'reason_past' => 'This date has passed.',
        'reason_too_soon' => 'We need at least :days days notice.',
        'reason_too_far' => 'We take bookings up to :months months ahead.',
        'reason_full_day' => 'A full-day booking already covers this date.',
    ],

    'errors' => [
        'slot_taken' => 'Sorry — that date and session were confirmed by another couple while you were filling this in. Please choose another.',
    ],

];
