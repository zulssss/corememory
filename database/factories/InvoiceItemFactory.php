<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InvoiceItem> */
class InvoiceItemFactory extends Factory
{
    protected $model = InvoiceItem::class;

    public function definition(): array
    {
        $unit = $this->faker->numberBetween(5, 60) * 10000;
        $qty = 1;

        return [
            'invoice_id' => Invoice::factory(),
            'description' => $this->faker->sentence(3),
            'qty' => $qty,
            'unit_price_cents' => $unit,
            'total_cents' => $unit * $qty,
            'sort_order' => 0,
        ];
    }
}
