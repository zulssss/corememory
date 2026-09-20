<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BlockedDate;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BlockedDate> */
class BlockedDateFactory extends Factory
{
    protected $model = BlockedDate::class;

    public function definition(): array
    {
        return [
            'date' => now()->addMonths(4)->startOfDay(),
            'session_slot' => null,
            'reason' => 'Studio leave',
        ];
    }
}
