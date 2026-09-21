<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CostCategory;
use App\Models\Booking;
use App\Models\BookingCost;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BookingCost> */
class BookingCostFactory extends Factory
{
    protected $model = BookingCost::class;

    public function definition(): array
    {
        $category = $this->faker->randomElement(CostCategory::cases());

        return [
            'booking_id' => Booking::factory(),
            'label' => $category->label(),
            'category' => $category,
            'amount_cents' => $this->faker->numberBetween(2, 30) * 10000,
            'is_paid' => $this->faker->boolean(70),
        ];
    }

    public function of(int $cents): static
    {
        return $this->state(fn () => ['amount_cents' => $cents]);
    }
}
