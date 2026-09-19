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

    // Placeholder contact details — replaced from admin settings in Phase 2.
    'contact' => [
        'phone' => '+60 12-345 6789',
        'phone_href' => '+60123456789',
        'email' => 'hello@corememory.test',
    ],

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
