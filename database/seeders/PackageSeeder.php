<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Package;
use Illuminate\Database\Seeder;

/**
 * Demo packages. Prices and inclusions are placeholders — the studio replaces
 * them in Admin → Packages. They are shaped like real Malaysian wedding
 * packages so the pricing page can be judged properly.
 *
 * Every inclusion the brief asks for is stated explicitly: hours of coverage,
 * number of edited photos, crew on site, delivery turnaround, raw files,
 * travel radius, album. A couple should never have to ask what they get.
 */
class PackageSeeder extends Seeder
{
    private const PACKAGES = [
        [
            'name' => 'Nikah Essentials',
            'price_cents' => 380000,
            'price_is_from' => true,
            'duration_hours' => 4,
            'is_popular' => false,
            'description' => 'Solemnisation coverage for couples who want the ceremony documented properly, without a full-day crew.',
            'inclusions' => [
                '4 hours of coverage',
                '300+ edited photographs',
                '1 photographer on site',
                'Delivered in 3 weeks',
                'Raw files not included',
                'Travel within Klang Valley included',
                'Online gallery for 12 months',
            ],
        ],
        [
            'name' => 'Wedding Classic',
            'price_cents' => 680000,
            'price_is_from' => false,
            'duration_hours' => 8,
            'is_popular' => true,
            'description' => 'Our most-booked package. Full-day coverage with a second shooter, from morning preparations to the last of the evening guests.',
            'inclusions' => [
                '8 hours of coverage',
                '600+ edited photographs',
                '2 photographers on site',
                'Delivered in 4 weeks',
                'Raw files included on request',
                'Travel within Klang Valley included',
                '20-page layflat album',
                'Online gallery for 24 months',
            ],
        ],
        [
            'name' => 'Wedding Signature',
            'price_cents' => 980000,
            'price_is_from' => false,
            'duration_hours' => 12,
            'is_popular' => false,
            'description' => 'Photo and video across the whole day, with a same-day edit screened at the reception.',
            'inclusions' => [
                '12 hours of coverage',
                '900+ edited photographs',
                '2 photographers and 2 videographers',
                'Same-day edit screened at the reception',
                '5-minute highlight film',
                'Delivered in 6 weeks',
                'Raw files included',
                'Travel anywhere in Peninsular Malaysia included',
                '40-page layflat album',
                'Online gallery for 24 months',
            ],
        ],
        [
            'name' => 'Pre-wedding Story',
            'price_cents' => 250000,
            'price_is_from' => true,
            'duration_hours' => 4,
            'is_popular' => false,
            'description' => 'A relaxed half-day session at one location, before the wedding itself.',
            'inclusions' => [
                '4 hours of coverage',
                '150+ edited photographs',
                '1 photographer on site',
                'One location',
                'Delivered in 2 weeks',
                'Raw files not included',
                'Travel within Klang Valley included',
            ],
        ],
    ];

    public function run(): void
    {
        foreach (self::PACKAGES as $index => $package) {
            Package::create([...$package, 'is_active' => true, 'sort_order' => $index]);
        }
    }
}
