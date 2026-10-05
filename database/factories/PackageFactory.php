<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PackageCategory;
use App\Models\Package;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Package> */
class PackageFactory extends Factory
{
    protected $model = Package::class;

    public function definition(): array
    {
        return [
            'name' => 'Package '.$this->faker->unique()->word(),
            'category' => PackageCategory::Photo,
            'price_cents' => $this->faker->numberBetween(20, 120) * 10000,
            'price_is_from' => false,
            'duration_hours' => $this->faker->randomElement([4, 6, 8, 10]),
            'inclusions' => ['8 hours coverage', '300 edited photos', 'Online gallery'],
            'description' => $this->faker->sentence(12),
            'is_popular' => false,
            'is_active' => true,
            'sort_order' => $this->faker->numberBetween(0, 20),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function priced(int $cents): static
    {
        return $this->state(fn () => ['price_cents' => $cents]);
    }
}
