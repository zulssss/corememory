<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Enums\EnquirySource;
use App\Models\Booking;
use App\Models\Package;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Booking> */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        $subtotal = $this->faker->numberBetween(30, 100) * 10000;
        $addOns = $this->faker->numberBetween(0, 20) * 10000;
        $total = $subtotal + $addOns;

        return [
            // Tests create bookings directly; the real flow allocates from the
            // sequences table via CreateBooking.
            'reference' => 'CM-'.now()->year.'-'.$this->faker->unique()->numberBetween(1000, 9999),
            'package_id' => Package::factory(),
            'partner_one_name' => $this->faker->firstName(),
            'partner_two_name' => $this->faker->firstName(),
            'email' => $this->faker->safeEmail(),
            'phone' => '01'.$this->faker->numberBetween(1, 9).$this->faker->numerify('-### ####'),
            'guest_count' => $this->faker->numberBetween(50, 600),
            'source' => $this->faker->randomElement(EnquirySource::cases()),
            'notes' => $this->faker->optional()->sentence(),
            'subtotal_cents' => $subtotal,
            'addons_total_cents' => $addOns,
            'estimated_total_cents' => $total,
            'deposit_cents' => (int) round($total * 0.3),
            'status' => BookingStatus::Pending,
        ];
    }

    public function status(BookingStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }

    public function confirmed(): static
    {
        return $this->status(BookingStatus::Confirmed);
    }

    public function stale(?int $days = null): static
    {
        $days = $days ?? (int) config('booking.pending_lapse_days') + 1;

        return $this->state(fn () => [
            'status' => BookingStatus::Pending,
            'created_at' => now()->subDays($days),
        ]);
    }
}
