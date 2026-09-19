<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Post;
use Illuminate\Database\Seeder;

/** Demo journal posts. The journal itself is built in Phase 5. */
class PostSeeder extends Seeder
{
    private const POSTS = [
        [
            'title' => 'What to expect on your wedding day',
            'excerpt' => 'A walk through how we work from the morning preparations to the last of the evening guests, so nothing on the day is a surprise.',
        ],
        [
            'title' => 'Why we shoot nikah and reception differently',
            'excerpt' => 'Two ceremonies, two moods, two approaches — and why treating them as one job produces worse photographs of both.',
        ],
        [
            'title' => 'Choosing a venue that photographs well',
            'excerpt' => 'Light, ceilings and space matter more than decoration. What we look for when couples ask us to weigh in early.',
        ],
    ];

    public function run(): void
    {
        foreach (self::POSTS as $index => $post) {
            Post::create([
                'title' => $post['title'],
                'excerpt' => $post['excerpt'],
                'body' => $post['excerpt']."\n\nThis is placeholder copy, replaced by the studio in the admin panel.",
                'published_at' => now()->subWeeks($index + 1),
            ]);
        }
    }
}
