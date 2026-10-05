<?php

declare(strict_types=1);

/*
| Every user-facing string on the public site.
|
| Copy is English, but nothing is hardcoded in a Blade template — so adding
| Bahasa Malaysia later means creating lang/ms/site.php and setting the locale.
| No template changes, ever.
|
| Placeholder values below are marked. They are replaced from admin settings in
| Phase 2, never invented as if real.
*/

return [

    'brand' => [
        'name' => 'CoreMemory',
        'legal_name' => 'CoreMemory Studio',
    ],

    'nav' => [
        'primary' => 'Primary navigation',
        'home' => 'Home',
        'work' => 'Work',
        'packages' => 'Packages',
        'about' => 'About',
        'journal' => 'Journal',
        'contact' => 'Contact',
        'menu' => 'Menu',
        'close' => 'Close',
    ],

    'cta' => [
        'check_availability' => 'Check availability',
        'see_all' => 'See all',
        'see_all_work' => 'See all work',
        'read_the_journal' => 'Read the journal',
        'view' => 'View',
        'view_packages' => 'View packages',
        'start_booking' => 'Start your booking',
    ],

    'skip_to_content' => 'Skip to content',

    'media' => [
        'placeholder' => 'Placeholder',
        'alt_project' => 'Photograph from :title',
        'alt_bts' => 'Behind the scenes with the CoreMemory team',
    ],

    'categories' => [
        'wedding' => 'Wedding',
        'pre_wedding' => 'Pre-wedding',
        'nikah' => 'Nikah',
        'engagement' => 'Engagement',
        'video' => 'Video',
    ],

    'work' => [
        'headline' => 'Every wedding we have photographed, in full.',
        'meta_description' => 'Wedding, nikah, pre-wedding and engagement photography from CoreMemory, across Malaysia.',
        'filter_label' => 'Filter by category',
        'all' => 'All',
        'empty' => 'No stories published in this category yet.',
        'next' => 'Next story',
        'previous' => 'Previous story',
    ],

    'meta' => [
        'tagline' => 'Wedding photography in Malaysia',
        'default_description' => 'Wedding photography and videography in Malaysia. Transparent pricing, published packages, and a booking process that starts with your date.',
    ],

    // Placeholder editorial copy — replaced from admin settings in Phase 2.
    'hero' => [
        'headline' => 'We photograph weddings the way they actually feel — unhurried, honest, and yours.',
        'location' => 'Kuala Lumpur, Malaysia',
        'scroll' => 'Scroll',
    ],

    'statement' => [
        'eyebrow' => 'Based in Kuala Lumpur, working across Malaysia',
        'lead' => 'At CoreMemory, we believe a wedding photograph should outlive the day it was taken.',
        'rest' => 'So we publish our prices openly, we tell you what is included before you ask, and we spend our time photographing your day rather than negotiating it.',
    ],

    'featured' => [
        'blurb' => 'A two-day celebration across a nikah in the morning and a reception the following evening — photographed as one continuous story rather than two separate jobs.',
    ],

    'meta_labels' => [
        'couple' => 'Couple',
        'date' => 'Date',
        'venue' => 'Venue',
        'crew' => 'Crew',
    ],

    'stats' => [
        'years' => 'Years of experience',
        'weddings' => 'Weddings photographed',
        'awards' => 'Awards received',
        'couples' => 'Happy couples',
    ],

    'testimonials' => [
        'one' => 'We knew the price before we ever sent a message, which made the whole thing feel honest from the start. The photos came back exactly as promised.',
        'two' => 'They handled both the nikah and the reception without us having to explain anything twice. Calm the entire day.',
        'three' => 'Booking took ten minutes. We picked our date, saw the total, and that was it.',
    ],

    /*
     * Contact details deliberately do NOT live here. They are owner-editable
     * settings (contact.phone, contact.email, social.instagram) read straight
     * from Settings, so an admin edit is reflected everywhere at once. The
     * hardcoded values that used to sit here were fake and unreachable.
     */

    'social' => [
        'instagram' => 'Instagram',
        'tiktok' => 'TikTok',
        'whatsapp' => 'WhatsApp',
    ],

    'footer' => [
        'get_in_touch' => 'Get in touch',
        'social' => 'Social links',
        'legal' => 'Legal',
        'privacy' => 'Privacy policy',
        'terms' => 'Terms of service',
    ],

    'packages' => [
        'from' => 'From',
        'popular' => 'Most popular',
        'on_request' => 'On request',

        'headline' => 'Our prices, in full, with nothing held back.',
        'meta_description' => 'Wedding photography packages and prices in Malaysia. Every price, inclusion and add-on published openly.',
        'intro' => 'Every package below shows what it costs and exactly what is included. You should never have to send a message to find out a price.',

        'whats_included' => 'What is included',
        'included' => 'Included',
        'not_included' => 'Not included',
        'compare' => 'Compare packages',
        'add_ons' => 'Add-ons',
        'add_ons_intro' => 'Add any of these to any package. Prices are per booking unless stated otherwise.',
        'each' => 'each',
        'up_to' => 'up to :max',

        'coverage' => 'Coverage',
        'hours' => ':count hours',
        'choose' => 'Choose this package',

        'deposit_note' => 'A :amount deposit per event locks your date. The balance is due 2 days before the event.',

        // The pricelist is organised by what is being captured, because that
        // is the choice a couple makes first.
        'categories' => [
            'photo' => 'Photo',
            'video' => 'Video',
            'photo_video' => 'Photo & Video',
            'session' => 'Sessions',
        ],

        'category_notes' => [
            'photo' => 'A single event is your solemnisation or your reception. A double event is both, and buys nine hours instead of six.',
            'video' => 'A single event is your solemnisation or your reception. A double event is both, and buys nine hours instead of six.',
            'photo_video' => 'Both crews on the same day. A single event is your solemnisation or your reception; a double event is both.',
            'session' => 'Standalone shoots, away from the wedding day itself.',
        ],

        'honest_note_label' => 'What can change a quote',
        'honest_note' => 'Two things move a price: how much of the day we cover, and how far we travel. Transportation is added based on your venue, and we are happy to travel anywhere. Tell us your date and venue and we will confirm the exact figure — no obligation, and no pressure.',
    ],

    'sections' => [
        'selected_work' => 'Selected work',
        'our_packages' => 'Our packages',
        'words_from_couples' => 'Words from our couples',
        'stories_behind' => 'Stories behind the shots',
        'behind_the_scenes' => 'Behind the scenes',
    ],

    'placeholder_page' => [
        'label' => 'Coming soon',
        'body' => 'This page is built in :phase. The layout shell, design tokens and motion system are already in place.',
    ],

];
