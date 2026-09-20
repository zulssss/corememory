<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\BlockedDate;
use App\Services\AvailabilityService;

/**
 * Keeps slot_holds in step with what the studio blocks in the admin panel.
 *
 * Without this, blocking a date would change a row in blocked_dates and
 * nothing else — the public calendar would go on offering the date, which is
 * the worst possible failure for a feature whose entire job is to say "no".
 *
 * Holds are written through AvailabilityService, so a block that collides with
 * an already-confirmed booking raises SlotUnavailableException rather than
 * silently winning.
 */
class BlockedDateObserver
{
    public function __construct(
        private readonly AvailabilityService $availability,
    ) {}

    public function saved(BlockedDate $blockedDate): void
    {
        // Release first: editing a block from Morning to Evening must give
        // the morning back.
        $this->availability->release($blockedDate);

        $this->availability->hold(
            $blockedDate,
            $blockedDate->date,
            $blockedDate->slot(),
            $blockedDate->reason ?: __('booking.availability.reason_blocked'),
        );
    }

    public function deleted(BlockedDate $blockedDate): void
    {
        $this->availability->release($blockedDate);
    }
}
