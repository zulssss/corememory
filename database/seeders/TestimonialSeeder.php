<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Project;
use App\Models\Testimonial;
use Illuminate\Database\Seeder;

/** Demo testimonials, linked to seeded projects where the names match. */
class TestimonialSeeder extends Seeder
{
    private const QUOTES = [
        [
            'couple' => 'Aisyah & Danial',
            'context' => 'Wedding, 2026',
            'quote' => 'We knew the price before we ever sent a message, which made the whole thing feel honest from the start. The photos came back exactly as promised.',
        ],
        [
            'couple' => 'Farah & Hafiz',
            'context' => 'Nikah, 2025',
            'quote' => 'They handled both the nikah and the reception without us having to explain anything twice. Calm the entire day.',
        ],
        [
            'couple' => 'Mei Ling & Wei Sheng',
            'context' => 'Pre-wedding, 2025',
            'quote' => 'Booking took ten minutes. We picked our date, saw the total, and that was it. No back and forth.',
        ],
        [
            'couple' => 'Nurul & Iskandar',
            'context' => 'Engagement, 2025',
            'quote' => 'The team stayed out of the way and still caught every moment that mattered to our families.',
        ],
        [
            'couple' => 'Priya & Arun',
            'context' => 'Wedding, 2024',
            'quote' => 'Two ceremonies, two venues, one crew who knew exactly what was happening next. Worth every ringgit.',
        ],
    ];

    public function run(): void
    {
        foreach (self::QUOTES as $index => $entry) {
            Testimonial::create([
                'couple_name' => $entry['couple'],
                'quote' => $entry['quote'],
                'context' => $entry['context'],
                'project_id' => Project::where('title', $entry['couple'])->value('id'),
                'is_published' => true,
                'sort_order' => $index,
            ]);
        }
    }
}
