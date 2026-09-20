<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SessionSlot;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A claim on one concrete date + slot.
 *
 * Never create these by hand — go through App\Services\AvailabilityService or
 * the booking Actions, which write them inside a transaction and translate the
 * unique-constraint violation into a clear error.
 *
 * session_slot here is ALWAYS concrete. 'full_day' must never appear.
 */
class SlotHold extends Model
{
    protected $fillable = ['event_date', 'session_slot', 'reason'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'session_slot' => SessionSlot::class,
        ];
    }

    public function holdable(): MorphTo
    {
        return $this->morphTo();
    }
}
