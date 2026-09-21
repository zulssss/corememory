<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EnquiryStatus;
use App\Models\Enquiry;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Enquiry> */
class EnquiryFactory extends Factory
{
    protected $model = Enquiry::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->safeEmail(),
            'phone' => '01'.$this->faker->numberBetween(1, 9).$this->faker->numerify('-### ####'),
            'message' => $this->faker->paragraph(),
            'status' => EnquiryStatus::New,
        ];
    }

    public function replied(): static
    {
        return $this->state(fn () => ['status' => EnquiryStatus::Replied, 'replied_at' => now()]);
    }
}
