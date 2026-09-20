<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SessionSlot;
use App\Exceptions\SlotUnavailableException;
use App\Models\SlotHold;
use App\Support\SlotAvailability;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * THE AVAILABILITY ENGINE.
 *
 * Read CLAUDE.md § Availability before changing anything here.
 *
 * The one idea that makes this work: `full_day` is NEVER stored. It expands
 * into the three concrete slots. So a single UNIQUE(event_date, session_slot)
 * index on slot_holds enforces every conflict rule, and there is no
 * application logic that can get the matrix wrong:
 *
 *   Full Day onto a date with an existing Morning -> collides on morning
 *   Morning onto an existing Full Day             -> collides on morning
 *   The same slot twice                           -> collides
 *   Morning + Evening on one date                 -> both fit, correctly
 *   Admin block vs confirmed booking              -> collides, shared namespace
 *
 * Holds exist only for blocking statuses. A pending enquiry writes nothing,
 * which is exactly what makes it tentative rather than held.
 */
class AvailabilityService
{
    /**
     * Per-slot state for every date in a range.
     *
     * Dates may be passed as Carbon instances or plain 'Y-m-d' strings —
     * request input and wizard state arrive as strings, and making every
     * caller parse first just moves the same parse around.
     *
     * Two queries total, regardless of how many days are requested — never one
     * query per date. The wizard's calendar and /availability both use this.
     *
     * @return array<string, array{date: string, in_window: bool, window_reason: string|null, slots: array<string, SlotAvailability>}>
     */
    public function calendar(CarbonInterface|string $from, CarbonInterface|string $to): array
    {
        $from = CarbonImmutable::parse($from)->startOfDay();
        $to = CarbonImmutable::parse($to)->startOfDay();

        $holds = $this->holdsBetween($from, $to);
        $tentative = $this->tentativeSlotsBetween($from, $to);

        $calendar = [];

        foreach (CarbonPeriod::create($from, $to) as $day) {
            $key = $day->toDateString();
            $windowReason = $this->windowReason($day);

            $calendar[$key] = [
                'date' => $key,
                'in_window' => $windowReason === null,
                'window_reason' => $windowReason,
                'slots' => $this->slotsForDate(
                    $holds[$key] ?? [],
                    $tentative[$key] ?? [],
                    $windowReason,
                ),
            ];
        }

        return $calendar;
    }

    /** Can this exact date + slot be booked right now? */
    public function isAvailable(CarbonInterface|string $date, SessionSlot $slot): bool
    {
        return $this->check($date, $slot)->bookable;
    }

    /** The full state of one date + slot, including the reason it isn't bookable. */
    public function check(CarbonInterface|string $date, SessionSlot $slot): SlotAvailability
    {
        $day = CarbonImmutable::parse($date)->startOfDay();
        $key = $day->toDateString();

        $holds = $this->holdsBetween($day, $day);
        $tentative = $this->tentativeSlotsBetween($day, $day);

        return $this->slotsForDate(
            $holds[$key] ?? [],
            $tentative[$key] ?? [],
            $this->windowReason($day),
        )[$slot->value];
    }

    /**
     * Claim a date + slot for a BookingDate or a BlockedDate.
     *
     * MUST be called inside a transaction. The insert is the lock: if another
     * transaction committed the same pair first, MySQL raises error 1062 and
     * we convert it into a SlotUnavailableException rather than letting a
     * duplicate through.
     *
     * There is deliberately no pre-check here. Checking then inserting leaves a
     * race window between the two; relying on the unique index closes it.
     *
     * @throws SlotUnavailableException
     */
    public function hold(Model $holdable, CarbonInterface|string $date, SessionSlot $slot, ?string $reason = null): void
    {
        $day = CarbonImmutable::parse($date)->startOfDay();

        foreach ($slot->expand() as $concrete) {
            try {
                $hold = new SlotHold([
                    'event_date' => $day,
                    'session_slot' => $concrete,
                    'reason' => $reason,
                ]);

                $hold->holdable()->associate($holdable);
                $hold->save();
            } catch (QueryException $e) {
                if ($this->isUniqueViolation($e)) {
                    throw SlotUnavailableException::for($day, $concrete);
                }

                throw $e;
            }
        }
    }

    /** Give back every slot a booking date or block was holding. */
    public function release(Model $holdable): void
    {
        SlotHold::query()
            ->where('holdable_type', $holdable->getMorphClass())
            ->where('holdable_id', $holdable->getKey())
            ->delete();
    }

    /**
     * Would holding all of these succeed? A cheap pre-flight for the wizard,
     * so a couple is told about a clash before they fill anything in.
     *
     * This is NOT the guarantee — hold() is. This only avoids wasting the
     * couple's time on a slot that is already obviously gone.
     *
     * @param  iterable<array{date: CarbonInterface, slot: SessionSlot}>  $requests
     * @return list<string> human-readable problems, empty when all are free
     */
    public function problemsWith(iterable $requests): array
    {
        $problems = [];
        $seen = [];

        foreach ($requests as $request) {
            $date = CarbonImmutable::parse($request['date'])->startOfDay();
            $slot = $request['slot'];

            $state = $this->check($date, $slot);

            if (! $state->bookable) {
                $problems[] = $date->translatedFormat('d M Y').' — '
                    .$slot->label().': '.($state->reason ?? __('booking.availability.blocked'));

                continue;
            }

            // Two dates inside the SAME booking must not collide with each
            // other either — the database would reject the second insert, but
            // catching it here gives a much clearer message.
            foreach ($slot->expand() as $concrete) {
                $key = $date->toDateString().'|'.$concrete->value;

                if (isset($seen[$key])) {
                    $problems[] = $date->translatedFormat('d M Y').' — '
                        .__('booking.errors.duplicate_slot_in_booking');

                    break;
                }

                $seen[$key] = true;
            }
        }

        return $problems;
    }

    /*
    |--------------------------------------------------------------------------
    | The booking window
    |--------------------------------------------------------------------------
    */

    public function earliestDate(): CarbonImmutable
    {
        return CarbonImmutable::today()->addDays((int) config('booking.min_lead_days'));
    }

    public function latestDate(): CarbonImmutable
    {
        return CarbonImmutable::today()->addMonths((int) config('booking.max_ahead_months'));
    }

    /** Null when the date is inside the window; otherwise why it isn't. */
    public function windowReason(CarbonInterface|string $date): ?string
    {
        $day = CarbonImmutable::parse($date)->startOfDay();

        if ($day->isBefore(CarbonImmutable::today())) {
            return __('booking.availability.reason_past');
        }

        if ($day->isBefore($this->earliestDate())) {
            return __('booking.availability.reason_too_soon', [
                'days' => config('booking.min_lead_days'),
            ]);
        }

        if ($day->isAfter($this->latestDate())) {
            return __('booking.availability.reason_too_far', [
                'months' => config('booking.max_ahead_months'),
            ]);
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /**
     * Held concrete slots, keyed by date then slot value, with the reason.
     *
     * @return array<string, array<string, string>>
     */
    private function holdsBetween(CarbonInterface|string $from, CarbonInterface|string $to): array
    {
        $from = CarbonImmutable::parse($from);
        $to = CarbonImmutable::parse($to);

        return SlotHold::query()
            ->whereBetween('event_date', [$from->toDateString(), $to->toDateString()])
            ->get(['event_date', 'session_slot', 'holdable_type', 'reason'])
            ->reduce(function (array $carry, SlotHold $hold): array {
                $date = $hold->event_date->toDateString();

                $carry[$date][$hold->session_slot->value] = $hold->reason
                    ?: __('booking.availability.reason_booked');

                return $carry;
            }, []);
    }

    /**
     * Concrete slots with a pending (tentative) enquiry against them.
     *
     * These do NOT block. They surface the "another enquiry is pending"
     * notice so a couple knows they may be in a race.
     *
     * @return array<string, array<string, true>>
     */
    private function tentativeSlotsBetween(CarbonInterface|string $from, CarbonInterface|string $to): array
    {
        $from = CarbonImmutable::parse($from);
        $to = CarbonImmutable::parse($to);

        $rows = DB::table('booking_dates')
            ->join('bookings', 'bookings.id', '=', 'booking_dates.booking_id')
            ->whereIn('bookings.status', config('booking.tentative_statuses', []))
            ->whereBetween('booking_dates.event_date', [$from->toDateString(), $to->toDateString()])
            ->get(['booking_dates.event_date', 'booking_dates.session_slot']);

        $tentative = [];

        foreach ($rows as $row) {
            $date = CarbonImmutable::parse($row->event_date)->toDateString();
            $slot = SessionSlot::from($row->session_slot);

            // Expand here too: a pending full-day enquiry makes all three
            // concrete slots tentative.
            foreach ($slot->expand() as $concrete) {
                $tentative[$date][$concrete->value] = true;
            }
        }

        return $tentative;
    }

    /**
     * Resolve all four slot options for one date.
     *
     * @param  array<string, string>  $heldSlots  concrete slot => reason
     * @param  array<string, true>  $tentativeSlots
     * @return array<string, SlotAvailability>
     */
    private function slotsForDate(array $heldSlots, array $tentativeSlots, ?string $windowReason): array
    {
        $slots = [];

        foreach (SessionSlot::cases() as $slot) {
            if ($windowReason !== null) {
                $slots[$slot->value] = SlotAvailability::outOfWindow($slot, $windowReason);

                continue;
            }

            // A slot is blocked if ANY of the concrete slots it consumes is
            // held. For Full Day that means all three must be free — which is
            // how "a single-slot booking blocks Full Day" falls out for free.
            $blockingReason = null;

            foreach ($slot->expand() as $concrete) {
                if (isset($heldSlots[$concrete->value])) {
                    $blockingReason = $slot->isFullDay()
                        ? __('booking.availability.reason_full_day_partly_taken')
                        : $heldSlots[$concrete->value];

                    break;
                }
            }

            if ($blockingReason !== null) {
                $slots[$slot->value] = SlotAvailability::blocked($slot, $blockingReason);

                continue;
            }

            $isTentative = collect($slot->expand())
                ->contains(fn (SessionSlot $c) => isset($tentativeSlots[$c->value]));

            $slots[$slot->value] = $isTentative
                ? SlotAvailability::tentative($slot)
                : SlotAvailability::available($slot);
        }

        return $slots;
    }

    /** MySQL 1062 / SQLSTATE 23000 — a unique constraint was violated. */
    private function isUniqueViolation(QueryException $e): bool
    {
        return $e->getCode() === '23000'
            || ($e->errorInfo[1] ?? null) === 1062;
    }
}
