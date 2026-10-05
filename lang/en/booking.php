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
        'reason_full_day_partly_taken' => 'Part of this date is already booked, so a full day is not available.',
    ],

    'wizard' => [
        'title' => 'Book your date',
        'headline' => 'Let us start with your date.',
        'intro' => 'Choose your date and session first, so we can tell you straight away whether we are free. Nothing is confirmed until we speak.',

        'steps' => [
            1 => 'Date',
            2 => 'Package',
            3 => 'Add-ons',
            4 => 'Your details',
            5 => 'Review',
        ],
        'step_of' => 'Step :current of :total',

        'add_another_date' => 'Add another date',
        'remove_date' => 'Remove',
        'date_label_hint' => 'e.g. Nikah, Reception',
        'choose_a_day' => 'Choose a day',
        'choose_a_session' => 'Choose a session',
        'date_incomplete' => 'Something is still missing for this date — tap it to finish.',
        'selected' => 'Selected',
        'choose' => 'Choose',
        'back_to_step' => 'Back to :step',
        'selected' => 'Selected',
        'no_date_yet' => 'No date chosen yet',
        'previous_month' => 'Previous month',
        'next_month' => 'Next month',
        'date_number' => 'Date :number',

        'running_total' => 'Estimated total',
        'deposit_line' => 'Deposit (:amount per event)',
        'balance_line' => 'Balance',
        'package_line' => 'Package',
        'add_ons_line' => 'Add-ons',
        'no_add_ons' => 'No add-ons chosen.',
        'add_ons_intro' => 'Optional. Add anything you need — your total updates as you go.',

        'next' => 'Continue',
        'back' => 'Back',
        'submit' => 'Send booking request',

        'review_headline' => 'One last look before you send.',
        'your_dates' => 'Your dates',
        'your_package' => 'Your package',
        'your_details' => 'Your details',
        'terms_label' => 'I understand this is a booking request, not a confirmation, that CoreMemory will contact me to confirm availability, and I have read the :terms.',
        'terms_link' => 'terms and conditions',
    ],

    'fields' => [
        'partner_one' => 'Your name',
        'partner_two' => 'Your partner\'s name',
        'email' => 'Email',
        'phone' => 'Phone',
        'guest_count' => 'Estimated guests',
        'source' => 'How did you find us?',
        'notes' => 'Anything else we should know?',
        'package' => 'package',
        'venue' => 'Venue',
        'city' => 'City',
        'state' => 'State',
        'label' => 'What is this day?',
        'optional' => 'Optional',
    ],

    'thanks' => [
        'label' => 'Request received',
        'headline' => 'Thank you — we have your request.',
        'reference' => 'Your reference',
        'what_next' => 'What happens next',
        'steps' => [
            'We check the date against our calendar and confirm whether we are free.',
            'We come back to you within 2 working days with a written quotation.',
            'If everything looks right, a deposit confirms your date.',
        ],
        'response_time' => 'We usually reply within 2 working days.',
        'whatsapp' => 'Message us on WhatsApp',
        'back_home' => 'Back to the homepage',
    ],

    'errors' => [
        'slot_taken' => 'Sorry — that date and session were confirmed by another couple while you were filling this in. Please choose another.',
        'duplicate_slot_in_booking' => 'You have chosen this date and session twice.',
        'no_dates' => 'Please choose at least one date.',
        'package_required' => 'Please choose a package.',
        'phone' => 'Please enter a Malaysian mobile number, for example 012-345 6789.',
        'terms' => 'Please confirm you understand this is a request, not a confirmation.',
        'too_many' => 'That is a few requests in a short time. Please try again in :minutes minutes, or message us on WhatsApp.',
        'not_sent' => 'Not sent yet',
        'date_required' => 'Please choose a date on the calendar.',
        'session_required' => 'Please choose a session for this date.',
        'venue_required' => 'Please tell us the venue.',
        'city_required' => 'Please tell us the city.',
        'state_required' => 'Please tell us the state.',
    ],

    'sources' => [
        'instagram' => 'Instagram',
        'tiktok' => 'TikTok',
        'google' => 'Google',
        'referral' => 'A friend or family member',
        'walk_in' => 'Walk-in',
        'other' => 'Somewhere else',
    ],

    'notes' => [
        'created' => 'Booking request received from the website.',
        'status_changed' => 'Status changed from :from to :to.',
        'lapsed' => 'Automatically cancelled — no response after :days days.',
    ],

    'whatsapp_template' => 'Hi :name, thanks for your booking request with CoreMemory (:reference). We would love to help with your day.',

];
