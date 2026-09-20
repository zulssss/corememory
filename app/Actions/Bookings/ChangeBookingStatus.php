<?php

declare(strict_types=1);

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;

/**
 * The single entry point the admin panel uses to change a booking's status.
 *
 * It routes to the right Action so nobody has to remember which statuses hold
 * a slot: assigning $booking->status directly would change the badge in the
 * UI while leaving slot_holds untouched, which is exactly how a cancelled
 * wedding keeps its Saturday or a confirmed one fails to reserve it.
 */
class ChangeBookingStatus
{
    public function __construct(
        private readonly ConfirmBooking $confirm,
        private readonly ReleaseBooking $release,
    ) {}

    public function handle(Booking $booking, BookingStatus $status, ?User $actor = null): Booking
    {
        return $status->blocksSlot()
            ? $this->confirm->handle($booking, $status, $actor)
            : $this->release->handle($booking, $status, $actor);
    }
}
