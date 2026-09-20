<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AddOn;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AddOn> */
class AddOnFactory extends Factory
{
    protected $model = AddOn::class;

    public function definition(): array
    {
        return [
            'name' => 'Add-on '.$this->faker->unique()->word(),
            'price_cents' => $this->faker->numberBetween(2, 20) * 10000,
            'description' => $this->faker->sentence(8),
            'is_quantifiable' => false,
            'max_qty' => 1,
            'applies_to' => null,
            'is_active' => true,
            'sort_order' => $this->faker->numberBetween(0, 20),
        ];
    }

    public function quantifiable(int $maxQty = 6): static
    {
        return $this->state(fn () => ['is_quantifiable' => true, 'max_qty' => $maxQty]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function priced(int $cents): static
    {
        return $this->state(fn () => ['price_cents' => $cents]);
    }

    /** @param  list<int>  $packageIds */
    public function onlyFor(array $packageIds): static
    {
        return $this->state(fn () => ['applies_to' => $packageIds]);
    }
}
