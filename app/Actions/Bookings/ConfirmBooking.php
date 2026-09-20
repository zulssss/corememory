<?php

declare(strict_types=1);

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Exceptions\SlotUnavailableException;
use App\Models\Booking;
use App\Models\User;
use App\Services\AvailabilityService;
use Illuminate\Support\Facades\DB;

/**
 * Moves a booking into a status that HOLDS its dates.
 *
 * This is where the double-booking guarantee actually lives.
 *
 * Every date on the booking is expanded into concrete slots and inserted into
 * slot_holds inside one transaction. The UNIQUE(event_date, session_slot)
 * index is the lock — no advisory locking, no SELECT ... FOR UPDATE, and no
 * window between checking and writing.
 *
 * Two admins confirming different bookings onto the same slot at the same
 * instant: exactly one commits, the other gets MySQL 1062 which surfaces as a
 * SlotUnavailableException. The whole transaction rolls back, so a booking is
 * never left half-confirmed with some of its dates held.
 */
class ConfirmBooking
{
    public function __construct(
        private readonly AvailabilityService $availability,
    ) {}

    /**
     * @throws SlotUnavailableException
     */
    public function handle(
        Booking $booking,
        BookingStatus $status = BookingStatus::Confirmed,
        ?User $actor = null,
    ): Booking {
        if (! $status->blocksSlot()) {
            throw new \InvalidArgumentException(
                "Status [{$status->value}] does not hold a slot. Use a status listed in config('booking.blocking_statuses')."
            );
        }

        return DB::transaction(function () use ($booking, $status, $actor): Booking {
            $booking->loadMissing('dates');

            $previous = $booking->status;

            // Release first so re-confirming an already-held booking, or
            // moving confirmed -> deposit_paid, doesn't collide with its own
            // existing holds.
            foreach ($booking->dates as $date) {
                $this->availability->release($date);
            }

            foreach ($booking->dates as $date) {
                $this->availability->hold(
                    $date,
                    $date->event_date,
                    $date->session_slot,
                    __('booking.availability.reason_booked'),
                );
            }

            $booking->forceFill([
                'status' => $status,
                'lapsed_at' => null,
            ])->save();

            $booking->notes()->create([
                'user_id' => $actor?->getKey(),
                'body' => __('booking.notes.status_changed', [
                    'from' => $previous->label(),
                    'to' => $status->label(),
                ]),
                'is_system' => true,
            ]);

            return $booking->fresh(['dates', 'addOns', 'package']);
        });
    }
}
