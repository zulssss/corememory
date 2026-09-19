<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ProjectCategory;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Project> */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        $couple = $this->faker->firstName().' & '.$this->faker->firstName();

        return [
            'title' => $couple,
            'category' => $this->faker->randomElement(ProjectCategory::cases()),
            'couple_names' => $couple,
            'event_date' => $this->faker->dateTimeBetween('-2 years', '-1 week'),
            'venue' => $this->faker->company(),
            'city' => $this->faker->city(),
            'state' => 'Selangor',
            'excerpt' => $this->faker->sentence(14),
            'description' => $this->faker->paragraphs(3, true),
            'crew' => [
                ['role' => 'Photographer', 'name' => $this->faker->firstName()],
                ['role' => 'Second shooter', 'name' => $this->faker->firstName()],
            ],
            'is_featured' => false,
            'sort_order' => $this->faker->numberBetween(0, 100),
            'published_at' => now()->subDays($this->faker->numberBetween(1, 400)),
        ];
    }

    public function featured(): static
    {
        return $this->state(fn () => ['is_featured' => true]);
    }

    public function draft(): static
    {
        return $this->state(fn () => ['published_at' => null]);
    }

    public function category(ProjectCategory $category): static
    {
        return $this->state(fn () => ['category' => $category]);
    }
}
