<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AddOn;
use Illuminate\Database\Seeder;

/**
 * Demo add-ons, each with its own price shown openly.
 *
 * `is_quantifiable` decides whether the wizard renders a stepper or a
 * checkbox: an extra hour is naturally x3, a drone is not.
 */
class AddOnSeeder extends Seeder
{
    private const ADD_ONS = [
        ['name' => 'Extra hour of coverage', 'price_cents' => 45000, 'quantifiable' => true, 'max' => 6,
            'description' => 'Added to the end of your booked coverage.'],
        ['name' => 'Second shooter', 'price_cents' => 90000, 'quantifiable' => false, 'max' => 1,
            'description' => 'An additional photographer for the full day.'],
        ['name' => 'Drone coverage', 'price_cents' => 65000, 'quantifiable' => false, 'max' => 1,
            'description' => 'Aerial stills and video, weather and venue permitting.'],
        ['name' => 'Same-day edit', 'price_cents' => 150000, 'quantifiable' => false, 'max' => 1,
            'description' => 'A short film edited on the day and screened at your reception.'],
        ['name' => 'Album upgrade', 'price_cents' => 80000, 'quantifiable' => false, 'max' => 1,
            'description' => 'Upgrade to a 40-page layflat album with a linen cover.'],
        ['name' => 'Out-of-state travel', 'price_cents' => 120000, 'quantifiable' => true, 'max' => 3,
            'description' => 'Per travel day outside the Klang Valley, covering transport and accommodation.'],
        ['name' => 'Express delivery', 'price_cents' => 70000, 'quantifiable' => false, 'max' => 1,
            'description' => 'Your full gallery delivered within 10 days.'],
        ['name' => 'Pre-wedding session', 'price_cents' => 200000, 'quantifiable' => false, 'max' => 1,
            'description' => 'A half-day pre-wedding shoot added to your wedding booking.'],
    ];

    public function run(): void
    {
        foreach (self::ADD_ONS as $index => $addOn) {
            AddOn::create([
                'name' => $addOn['name'],
                'price_cents' => $addOn['price_cents'],
                'description' => $addOn['description'],
                'is_quantifiable' => $addOn['quantifiable'],
                'max_qty' => $addOn['max'],
                'applies_to' => null,   // available with every package
                'is_active' => true,
                'sort_order' => $index,
            ]);
        }
    }
}
