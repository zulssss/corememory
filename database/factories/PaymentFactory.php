<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Payment> */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'amount_cents' => $this->faker->numberBetween(10, 50) * 10000,
            'paid_at' => today(),
            'method' => PaymentMethod::BankTransfer,
            'reference' => strtoupper($this->faker->bothify('TRX####??')),
        ];
    }

    public function of(int $cents): static
    {
        return $this->state(fn () => ['amount_cents' => $cents]);
    }
}
