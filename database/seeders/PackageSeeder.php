<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\PackageCategory;
use App\Models\Package;
use Illuminate\Database\Seeder;

/**
 * CoreMemory's real published pricelist.
 *
 * Two axes, straight from the studio's pricelist: what is captured (photo,
 * video, or both) and how many events are covered. A SINGLE event is one of
 * the solemnisation or the reception; a DOUBLE event is both, and buys nine
 * hours instead of six. Sessions are standalone shoots, not wedding days.
 *
 * Inclusions are reproduced from the pricelist. Only clear misspellings were
 * corrected ("Potraiture" -> "Portraiture"); the studio's wording and order
 * are otherwise untouched.
 */
class PackageSeeder extends Seeder
{
    /** Repeated verbatim across the photo packages. */
    private const PHOTO_BASE = [
        'Shoot and Edit',
        'Unlimited Shoot',
        'Best Edited Photo',
        'Copy Via Google Photo Share',
        'Free Outdoor | Portraiture',
    ];

    private const VIDEO_BASE = [
        'Colour Correction',
        'Copy Via Google Drive',
        'Free Outdoor | Portraiture',
    ];

    private const PACKAGES = [
        /* ---------------- PHOTO ---------------- */
        [
            'name' => 'Photo Basic — Single Event',
            'category' => 'photo',
            'price_cents' => 140000,
            'duration_hours' => 6,
            'description' => 'Solemnisation or reception.',
            'inclusions' => ['1 Photographer', 'Up to 6 Hours Coverage', ...self::PHOTO_BASE],
        ],
        [
            'name' => 'Photo Plus — Single Event',
            'category' => 'photo',
            'price_cents' => 200000,
            'duration_hours' => 6,
            'description' => 'Solemnisation or reception.',
            'inclusions' => ['2 Photographer', 'Up to 6 Hours Coverage', ...self::PHOTO_BASE],
        ],
        [
            'name' => 'Photo Basic — Double Event',
            'category' => 'photo',
            'price_cents' => 180000,
            'duration_hours' => 9,
            'description' => 'Solemnisation and reception.',
            'inclusions' => ['1 Photographer', 'Up to 9 Hours Coverage', ...self::PHOTO_BASE],
        ],
        [
            'name' => 'Photo Plus — Double Event',
            'category' => 'photo',
            'price_cents' => 300000,
            'duration_hours' => 9,
            'description' => 'Solemnisation and reception.',
            'inclusions' => [
                '2 Photographer',
                'Up to 9 Hours Coverage',
                'FREE Pre/Post Wedding Session Photo',
                ...self::PHOTO_BASE,
            ],
        ],

        /* ---------------- VIDEO ---------------- */
        [
            'name' => 'Video Basic — Single Event',
            'category' => 'video',
            'price_cents' => 140000,
            'duration_hours' => 6,
            'description' => 'Solemnisation or reception.',
            'inclusions' => ['1 Videographer', 'Up to 6 Hours Coverage', '3-5 Minutes Highlight', ...self::VIDEO_BASE],
        ],
        [
            'name' => 'Video Plus — Single Event',
            'category' => 'video',
            'price_cents' => 200000,
            'duration_hours' => 6,
            'description' => 'Solemnisation or reception.',
            'inclusions' => [
                '2 Videographer',
                'Up to 6 Hours Coverage',
                '30 Second Teaser',
                '3-5 Minutes Highlight',
                ...self::VIDEO_BASE,
            ],
        ],
        [
            'name' => 'Video Basic — Double Event',
            'category' => 'video',
            'price_cents' => 180000,
            'duration_hours' => 9,
            'description' => 'Solemnisation and reception.',
            'inclusions' => [
                '1 Videographer',
                'Up to 9 Hours Coverage',
                '30 Second Teaser',
                '3-5 Minutes Highlight',
                ...self::VIDEO_BASE,
            ],
        ],
        [
            'name' => 'Video Plus — Double Event',
            'category' => 'video',
            'price_cents' => 300000,
            'duration_hours' => 9,
            'description' => 'Solemnisation and reception.',
            'inclusions' => [
                '2 Videographer',
                'Up to 9 Hours Coverage',
                'FREE Pre/Post Wedding Session Video',
                '30 Second Teaser',
                '3-5 Minutes Highlight',
                ...self::VIDEO_BASE,
            ],
        ],

        /* ---------------- PHOTO & VIDEO ---------------- */
        [
            'name' => 'Photo & Video Basic — Single Event',
            'category' => 'photo_video',
            'price_cents' => 280000,
            'duration_hours' => 6,
            'description' => 'Solemnisation or reception.',
            'inclusions' => [
                '1 Photographer & 1 Videographer',
                'Up to 6 Hours Coverage',
                'Unlimited Shoot',
                'Best Edited Photo',
                'Colour Correction',
                '3-5 Minutes Highlight',
                'Copy Via Google Photo/Drive Share',
                'Free Outdoor | Portraiture',
            ],
        ],
        [
            'name' => 'Photo & Video Plus — Single Event',
            'category' => 'photo_video',
            'price_cents' => 390000,
            'duration_hours' => 6,
            'description' => 'Solemnisation or reception.',
            'inclusions' => [
                '2 Photographer & 2 Videographer',
                'Up to 6 Hours Coverage',
                'Unlimited Shoot',
                'Best Edited Photo',
                'Colour Correction',
                '30 Second Teaser',
                '3-5 Minutes Highlight',
                'Copy Via Google Photo/Drive Share',
                'Free Outdoor | Portraiture',
            ],
        ],
        [
            'name' => 'Photo & Video Basic — Double Event',
            'category' => 'photo_video',
            'price_cents' => 360000,
            'duration_hours' => 9,
            'description' => 'Solemnisation and reception.',
            'inclusions' => [
                '1 Photographer & 1 Videographer',
                'Up to 9 Hours Coverage',
                'Unlimited Shoot',
                'Best Edited Photo',
                'Colour Correction',
                '30 Second Teaser',
                '3-5 Minutes Highlight',
                'Copy Via Google Photo/Drive Share',
                'Free Outdoor | Portraiture',
            ],
        ],
        [
            'name' => 'Photo & Video Plus — Double Event',
            'category' => 'photo_video',
            'price_cents' => 600000,
            'duration_hours' => 9,
            'description' => 'Solemnisation and reception.',
            'inclusions' => [
                '2 Photographer & 2 Videographer',
                'Up to 9 Hours Coverage',
                'FREE Pre/Post Wedding Session Photo & Video',
                'Unlimited Shoot',
                'Best Edited Photo',
                'Colour Correction',
                '30 Second Teaser',
                '3-5 Minutes Highlight',
                'Copy Via Google Photo/Drive Share',
                'Free Outdoor | Portraiture',
            ],
        ],

        /* ---------------- SESSIONS ---------------- */
        [
            'name' => 'Pre/Post Wedding — Photo',
            'category' => 'session',
            'price_cents' => 90000,
            'duration_hours' => 2,
            'inclusions' => [
                '2 Hours Max Coverage',
                '1 Photographer',
                'Shoot and Edits',
                'Unlimited Shoot',
                'Best Edited Photo',
                'Send by Google Photo',
            ],
        ],
        [
            'name' => 'Pre/Post Wedding — Video',
            'category' => 'session',
            'price_cents' => 90000,
            'duration_hours' => 2,
            'inclusions' => [
                '2 Hours Max Coverage',
                '1 Videographer',
                'Colour Correction',
                '1-2 Minutes Duration',
                'Send by Google Drive',
            ],
        ],
        [
            'name' => 'Maternity — Photo',
            'category' => 'session',
            'price_cents' => 90000,
            'duration_hours' => 2,
            'inclusions' => [
                '2 Hours Max Coverage',
                '1 Photographer',
                'Shoot and Edits',
                'Unlimited Shoot',
                'Best Edited Photo',
                'Send by Google Photo',
            ],
        ],
        [
            'name' => 'Maternity — Video',
            'category' => 'session',
            'price_cents' => 90000,
            'duration_hours' => 2,
            'inclusions' => [
                '2 Hours Max Coverage',
                '1 Videographer',
                'Colour Correction',
                '1-2 Minutes Duration',
                'Send by Google Drive',
            ],
        ],
        [
            'name' => 'Engagement — Photo',
            'category' => 'session',
            'price_cents' => 90000,
            'duration_hours' => 4,
            'inclusions' => [
                '4 Hours Max Coverage',
                '1 Photographer',
                'Shoot and Edits',
                'Unlimited Shoot',
                'Free Outdoor | Portraiture',
                'Best Edited Photo',
                'Send by Google Photo',
            ],
        ],
        [
            'name' => 'Engagement — Video',
            'category' => 'session',
            'price_cents' => 90000,
            'duration_hours' => 4,
            'inclusions' => [
                '4 Hours Max Coverage',
                '1 Videographer',
                'Colour Correction',
                '2-3 Minutes Duration',
                'Free Outdoor | Portraiture',
                'Send by Google Drive',
            ],
        ],
    ];

    public function run(): void
    {
        $order = array_flip(array_column(PackageCategory::ordered(), 'value'));
        $keep = [];

        foreach (self::PACKAGES as $i => $package) {
            $slug = str($package['name'])->slug()->value();
            $keep[] = $slug;

            // updateOrCreate keyed on the slug so reseeding is idempotent and
            // never orphans a booking that already points at this package.
            Package::updateOrCreate(['slug' => $slug], [
                ...$package,
                'slug' => $slug,
                // Category first, then pricelist order within it.
                'sort_order' => ($order[$package['category']] * 100) + $i,
                'is_active' => true,
            ]);
        }

        // Anything left over is placeholder data from the original build.
        Package::whereNotIn('slug', $keep)->update(['is_active' => false]);
    }
}
