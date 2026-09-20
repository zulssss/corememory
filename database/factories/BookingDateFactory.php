<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SessionSlot;
use App\Models\Booking;
use App\Models\BookingDate;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BookingDate> */
class BookingDateFactory extends Factory
{
    protected $model = BookingDate::class;

    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'event_date' => now()->addMonths(3)->startOfDay(),
            'session_slot' => SessionSlot::Morning,
            'label' => null,
            'venue' => $this->faker->company(),
            'city' => $this->faker->city(),
            'state' => 'Selangor',
        ];
    }

    public function on(string $date, SessionSlot $slot): static
    {
        return $this->state(fn () => ['event_date' => $date, 'session_slot' => $slot]);
    }
}
