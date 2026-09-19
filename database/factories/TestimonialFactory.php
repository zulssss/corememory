<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Testimonial;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Testimonial> */
class TestimonialFactory extends Factory
{
    protected $model = Testimonial::class;

    public function definition(): array
    {
        return [
            'couple_name' => $this->faker->firstName().' & '.$this->faker->firstName(),
            'quote' => $this->faker->paragraph(3),
            'context' => $this->faker->randomElement(['Wedding', 'Nikah', 'Pre-wedding'])
                .', '.$this->faker->numberBetween(2024, 2026),
            'is_published' => true,
            'sort_order' => $this->faker->numberBetween(0, 50),
        ];
    }

    public function unpublished(): static
    {
        return $this->state(fn () => ['is_published' => false]);
    }
}
