<?php

declare(strict_types=1);

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use App\Services\AvailabilityService;
use Illuminate\Support\Facades\DB;

/**
 * Moves a booking to a status that does NOT hold its dates, and gives the
 * slots back to the calendar.
 *
 * Used for cancelling, and for moving a confirmed booking back to quoted or
 * contacted. Releasing is the mirror of ConfirmBooking: if holds are not
 * removed, a cancelled wedding silently keeps its Saturday forever.
 */
class ReleaseBooking
{
    public function __construct(
        private readonly AvailabilityService $availability,
    ) {}

    public function handle(
        Booking $booking,
        BookingStatus $status = BookingStatus::Cancelled,
        ?User $actor = null,
        ?string $reason = null,
    ): Booking {
        return DB::transaction(function () use ($booking, $status, $actor, $reason): Booking {
            $booking->loadMissing('dates');

            $previous = $booking->status;

            foreach ($booking->dates as $date) {
                $this->availability->release($date);
            }

            $booking->forceFill(['status' => $status])->save();

            $booking->notes()->create([
                'user_id' => $actor?->getKey(),
                'body' => __('booking.notes.status_changed', [
                    'from' => $previous->label(),
                    'to' => $status->label(),
                ]).($reason ? ' — '.$reason : ''),
                'is_system' => true,
            ]);

            return $booking->fresh(['dates', 'addOns', 'package']);
        });
    }
}
