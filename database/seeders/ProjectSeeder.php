<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ProjectCategory;
use App\Models\Project;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

/**
 * Seeds the portfolio with demo wedding stories and attaches placeholder
 * imagery from public/images/placeholder.
 *
 * THIS IS THE ONE SEEDER THAT REFERENCES PLACEHOLDER IMAGERY. When real
 * photography arrives, the studio uploads it in the admin panel and this
 * seeder stops being used for anything but local development.
 *
 * All couples, venues and quotes below are invented demo content. Nothing here
 * is a real client.
 */
class ProjectSeeder extends Seeder
{
    /**
     * Demo stories. Ratios are chosen so the homepage's asymmetric grid has
     * genuinely uneven images to lay out.
     *
     * @var list<array{title: string, category: ProjectCategory, venue: string, city: string, state: string, hero: string, gallery: list<string>, featured?: bool}>
     */
    private const STORIES = [
        [
            'title' => 'Aisyah & Danial',
            'category' => ProjectCategory::Wedding,
            'venue' => 'Dewan Perdana Felda',
            'city' => 'Kuala Lumpur',
            'state' => 'Wilayah Persekutuan',
            'hero' => '16x9-01.png',
            'gallery' => ['4x5-01.png', '3x2-01.png', '3x2-02.png', '4x5-02.png', '1x1-01.png'],
            'featured' => true,
        ],
        [
            'title' => 'Farah & Hafiz',
            'category' => ProjectCategory::Nikah,
            'venue' => 'Masjid Wilayah',
            'city' => 'Kuala Lumpur',
            'state' => 'Wilayah Persekutuan',
            'hero' => '3x2-03.png',
            'gallery' => ['4x5-03.png', '3x2-04.png', '1x1-02.png'],
        ],
        [
            'title' => 'Mei Ling & Wei Sheng',
            'category' => ProjectCategory::PreWedding,
            'venue' => 'Batu Caves',
            'city' => 'Gombak',
            'state' => 'Selangor',
            'hero' => '4x5-04.png',
            'gallery' => ['3x2-05.png', '4x5-05.png', '3x2-06.png'],
        ],
        [
            'title' => 'Nurul & Iskandar',
            'category' => ProjectCategory::Engagement,
            'venue' => 'The Majestic',
            'city' => 'Kuala Lumpur',
            'state' => 'Wilayah Persekutuan',
            'hero' => '3x2-07.png',
            'gallery' => ['1x1-03.png', '3x2-08.png'],
        ],
        [
            'title' => 'Priya & Arun',
            'category' => ProjectCategory::Wedding,
            'venue' => 'Sri Mahamariamman Temple',
            'city' => 'Kuala Lumpur',
            'state' => 'Wilayah Persekutuan',
            'hero' => '16x9-02.png',
            'gallery' => ['4x5-06.png', '3x2-09.png', '1x1-04.png'],
            'featured' => true,
        ],
        [
            'title' => 'Siti & Rahman',
            'category' => ProjectCategory::Video,
            'venue' => 'Tanarimba',
            'city' => 'Janda Baik',
            'state' => 'Pahang',
            'hero' => '16x9-03.png',
            'gallery' => ['3x2-10.png', '16x9-04.png'],
        ],
    ];

    public function run(): void
    {
        // Generate placeholder files if this is a clean checkout, so
        // `migrate:fresh --seed` works with no extra setup.
        if (! is_dir(public_path('images/placeholder'))) {
            Artisan::call('corememory:placeholders');
        }

        // Generate conversions synchronously while seeding. They are queued in
        // normal operation (the request must never wait on image processing),
        // but a seeded demo site has to look complete without anyone having to
        // remember to start a queue worker first.
        config()->set('media-library.queue_conversions_by_default', false);

        foreach (self::STORIES as $index => $story) {
            $project = Project::create([
                'title' => $story['title'],
                'category' => $story['category'],
                'couple_names' => $story['title'],
                'event_date' => now()->subMonths(($index + 1) * 3)->startOfMonth()->addDays(12),
                'venue' => $story['venue'],
                'city' => $story['city'],
                'state' => $story['state'],
                'excerpt' => 'A '.strtolower($story['category']->label())
                    .' at '.$story['venue'].', photographed across the full day.',
                'description' => $this->description($story['venue']),
                'crew' => [
                    ['role' => 'Photographer', 'name' => 'Zul'],
                    ['role' => 'Second shooter', 'name' => 'Amir'],
                    ['role' => 'Editor', 'name' => 'Hana'],
                ],
                'is_featured' => $story['featured'] ?? false,
                'sort_order' => $index,
                'published_at' => now()->subDays(($index + 1) * 9),
            ]);

            $this->attach($project, 'hero', [$story['hero']]);
            $this->attach($project, 'gallery', $story['gallery']);
        }
    }

    /**
     * @param  list<string>  $files
     */
    private function attach(Project $project, string $collection, array $files): void
    {
        foreach ($files as $file) {
            $path = public_path("images/placeholder/{$file}");

            if (! file_exists($path)) {
                continue;
            }

            // preservingOriginal: the placeholder files stay on disk so
            // re-seeding works. A real upload moves the file instead.
            $project->addMedia($path)
                ->preservingOriginal()
                ->usingFileName($project->slug.'-'.$file)
                ->toMediaCollection($collection);
        }
    }

    private function description(string $venue): string
    {
        return "Photographed at {$venue} over a single day, from the morning "
            ."preparations through to the last of the evening guests.\n\n"
            .'This is placeholder copy. The studio replaces it with the real '
            .'story in the admin panel — nothing here describes an actual client.';
    }
}
