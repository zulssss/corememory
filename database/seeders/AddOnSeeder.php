<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AddOn;
use Illuminate\Database\Seeder;

/**
 * CoreMemory's real add-on list, reproduced from the published pricelist.
 *
 * `quantifiable` decides whether the wizard renders a stepper or a checkbox:
 * anything the studio prices "per hour" or "per event" can be taken more than
 * once, a one-off deliverable cannot.
 *
 * Transportation is priced "RM100++ (Depends On Location)" on the pricelist,
 * so the RM100 here is a floor the studio adjusts per booking, not a final
 * figure. Its description says so rather than implying the quote is binding.
 */
class AddOnSeeder extends Seeder
{
    private const ADD_ONS = [
        ['name' => 'Additional Hour', 'price_cents' => 15000, 'quantifiable' => true, 'max' => 12,
            'description' => 'Per hour, per shooter, added to your booked coverage.'],
        ['name' => '3rd Shooter', 'price_cents' => 60000, 'quantifiable' => true, 'max' => 4,
            'description' => 'An additional photographer or videographer, per event.'],
        ['name' => 'Malam Berinai', 'price_cents' => 18000, 'quantifiable' => true, 'max' => 12,
            'description' => 'Per hour of henna-night coverage.'],
        ['name' => 'One Day Gap', 'price_cents' => 15000, 'quantifiable' => true, 'max' => 5,
            'description' => 'Where your events fall on separate days, covering hotel and the like.'],
        ['name' => 'Fast Edit Video (Same Day Edit)', 'price_cents' => 100000, 'quantifiable' => false, 'max' => 1,
            'description' => 'A film edited on the day of your event.'],
        ['name' => 'Fast Edit Photo/Video (3 Days)', 'price_cents' => 50000, 'quantifiable' => false, 'max' => 1,
            'description' => 'Your edited photos and video delivered within three days.'],
        ['name' => 'Transportation', 'price_cents' => 10000, 'quantifiable' => false, 'max' => 1,
            'description' => 'From RM100, depending on your venue. We confirm the exact figure with you before your date is locked.'],
        ['name' => 'Album 40 Pages', 'price_cents' => 70000, 'quantifiable' => true, 'max' => 5,
            'description' => 'A 40-page album, minimalist concept.'],
        ['name' => 'RAW Footage Photo and Video', 'price_cents' => 100000, 'quantifiable' => false, 'max' => 1,
            'description' => 'Unedited photo and video files from your event.'],
    ];

    public function run(): void
    {
        $keep = [];

        foreach (self::ADD_ONS as $index => $addOn) {
            $slug = str($addOn['name'])->slug()->value();
            $keep[] = $slug;

            // Keyed on the slug so reseeding never duplicates an add-on or
            // orphans one already attached to a booking.
            AddOn::updateOrCreate(['slug' => $slug], [
                'name' => $addOn['name'],
                'slug' => $slug,
                'price_cents' => $addOn['price_cents'],
                'description' => $addOn['description'],
                'is_quantifiable' => $addOn['quantifiable'],
                'max_qty' => $addOn['max'],
                'applies_to' => null,   // available with every package
                'is_active' => true,
                'sort_order' => $index,
            ]);
        }

        // Placeholder add-ons from the original build.
        AddOn::whereNotIn('slug', $keep)->update(['is_active' => false]);
    }
}
