<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Invoice> */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        $total = $this->faker->numberBetween(20, 90) * 10000;

        return [
            // Real invoices get their number from the locked sequence via
            // GenerateInvoice; tests that create records directly just need
            // something unique.
            'number' => 'CM-INV-'.now()->year.'-'.$this->faker->unique()->numberBetween(1000, 9999),
            'type' => InvoiceType::Deposit,
            'client_name' => $this->faker->name(),
            'client_email' => $this->faker->safeEmail(),
            'client_phone' => '012-345 6789',
            'issued_at' => today(),
            'due_at' => today()->addDays(7),
            'subtotal_cents' => $total,
            'discount_cents' => 0,
            'total_cents' => $total,
            'status' => InvoiceStatus::Draft,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn () => ['status' => InvoiceStatus::Sent, 'locked_at' => now()]);
    }

    public function paid(): static
    {
        return $this->state(fn () => ['status' => InvoiceStatus::Paid, 'locked_at' => now()]);
    }

    /** Sent and past its due date — what the receivables report cares about. */
    public function overdue(int $daysAgo = 45): static
    {
        return $this->state(fn () => [
            'status' => InvoiceStatus::Sent,
            'locked_at' => now(),
            'issued_at' => today()->subDays($daysAgo + 7),
            'due_at' => today()->subDays($daysAgo),
        ]);
    }

    public function totalling(int $cents): static
    {
        return $this->state(fn () => [
            'subtotal_cents' => $cents,
            'total_cents' => $cents,
        ]);
    }
}
