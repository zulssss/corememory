<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Post> */
class PostFactory extends Factory
{
    protected $model = Post::class;

    public function definition(): array
    {
        return [
            'title' => rtrim($this->faker->sentence(6), '.'),
            'excerpt' => $this->faker->sentence(18),
            'body' => $this->faker->paragraphs(6, true),
            'published_at' => now()->subDays($this->faker->numberBetween(1, 200)),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['published_at' => null]);
    }
}
