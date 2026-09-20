<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\SessionSlot;
use Carbon\CarbonInterface;
use RuntimeException;

/**
 * Thrown when a date + slot could not be held.
 *
 * Two ways this happens, and both must be reported plainly rather than
 * swallowed:
 *   1. The slot was already taken when we checked.
 *   2. We checked, it was free, and another transaction committed first —
 *      the unique index on slot_holds rejected our insert (MySQL 1062).
 *
 * Case 2 is the race the brief cares about. Never catch this and retry
 * silently: the couple must be told their slot is gone.
 */
class SlotUnavailableException extends RuntimeException
{
    public function __construct(
        public readonly CarbonInterface $date,
        public readonly SessionSlot $slot,
        ?string $message = null,
    ) {
        parent::__construct($message ?? __('booking.errors.slot_taken'));
    }

    public static function for(CarbonInterface $date, SessionSlot $slot): self
    {
        return new self($date, $slot);
    }
}
