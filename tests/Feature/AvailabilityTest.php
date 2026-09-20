<?php

declare(strict_types=1);

use App\Actions\Bookings\ConfirmBooking;
use App\Actions\Bookings\ReleaseBooking;
use App\Enums\BookingStatus;
use App\Enums\SessionSlot;
use App\Exceptions\SlotUnavailableException;
use App\Models\BlockedDate;
use App\Models\Booking;
use App\Models\BookingDate;
use App\Models\SlotHold;
use App\Services\AvailabilityService;

/*
|------------------------------------------------------------------------------
| THE AVAILABILITY ENGINE
|------------------------------------------------------------------------------
| The studio's worst outcome is two weddings booked onto one Saturday. These
| tests are the proof that cannot happen.
|
| The concurrent-submit case lives in tests/Concurrency/ — it needs two real
| database connections committing against each other, which RefreshDatabase
| (one wrapping transaction) cannot express.
*/

beforeEach(function () {
    $this->availability = app(AvailabilityService::class);

    // A date comfortably inside the booking window (14 days to 24 months).
    $this->date = now()->addMonths(6)->startOfDay()->toDateString();
});

/** Confirms a booking onto a date + slot, the way the studio would. */
function hold(string $date, SessionSlot $slot): Booking
{
    $booking = Booking::factory()->create();
    BookingDate::factory()->for($booking)->on($date, $slot)->create();

    return app(ConfirmBooking::class)->handle($booking);
}

describe('slot expansion', function () {
    it('writes three holds for a full day and one for a single slot', function () {
        hold($this->date, SessionSlot::FullDay);

        expect(SlotHold::count())->toBe(3)
            ->and(SlotHold::pluck('session_slot')->map->value->sort()->values()->all())
            ->toBe(['afternoon', 'evening', 'morning']);
    });

    it('never stores full_day as a hold', function () {
        // If full_day were ever stored, the unique index would stop protecting
        // anything — a full-day row and a morning row would not collide.
        hold($this->date, SessionSlot::FullDay);

        expect(SlotHold::where('session_slot', 'full_day')->exists())->toBeFalse();
    });
});

describe('the conflict matrix', function () {
    it('rejects the same slot booked twice', function () {
        hold($this->date, SessionSlot::Morning);

        expect(fn () => hold($this->date, SessionSlot::Morning))
            ->toThrow(SlotUnavailableException::class);
    });

    it('rejects a full day when a single slot is already taken', function () {
        hold($this->date, SessionSlot::Morning);

        expect($this->availability->isAvailable($this->date, SessionSlot::FullDay))->toBeFalse()
            ->and(fn () => hold($this->date, SessionSlot::FullDay))
            ->toThrow(SlotUnavailableException::class);
    });

    it('rejects a single slot when a full day is already taken', function () {
        hold($this->date, SessionSlot::FullDay);

        foreach (SessionSlot::concrete() as $slot) {
            expect($this->availability->isAvailable($this->date, $slot))->toBeFalse();
        }

        expect(fn () => hold($this->date, SessionSlot::Evening))
            ->toThrow(SlotUnavailableException::class);
    });

    it('allows two different slots on the same date', function () {
        hold($this->date, SessionSlot::Morning);

        expect($this->availability->isAvailable($this->date, SessionSlot::Evening))->toBeTrue();

        hold($this->date, SessionSlot::Evening);

        expect(SlotHold::count())->toBe(2);
    });

    it('allows the same slot on different dates', function () {
        $other = now()->addMonths(7)->startOfDay()->toDateString();

        hold($this->date, SessionSlot::Morning);
        hold($other, SessionSlot::Morning);

        expect(SlotHold::count())->toBe(2);
    });

    it('leaves the remaining slots free after a single-slot booking', function () {
        hold($this->date, SessionSlot::Afternoon);

        expect($this->availability->isAvailable($this->date, SessionSlot::Morning))->toBeTrue()
            ->and($this->availability->isAvailable($this->date, SessionSlot::Evening))->toBeTrue()
            ->and($this->availability->isAvailable($this->date, SessionSlot::Afternoon))->toBeFalse()
            ->and($this->availability->isAvailable($this->date, SessionSlot::FullDay))->toBeFalse();
    });
});

describe('admin blocks', function () {
    it('takes a whole day off the calendar', function () {
        BlockedDate::create(['date' => $this->date, 'session_slot' => null, 'reason' => 'Studio leave']);

        foreach (SessionSlot::cases() as $slot) {
            expect($this->availability->isAvailable($this->date, $slot))->toBeFalse();
        }
    });

    it('takes a single slot off the calendar', function () {
        BlockedDate::create(['date' => $this->date, 'session_slot' => SessionSlot::Morning, 'reason' => 'Dentist']);

        expect($this->availability->isAvailable($this->date, SessionSlot::Morning))->toBeFalse()
            ->and($this->availability->isAvailable($this->date, SessionSlot::Evening))->toBeTrue();
    });

    it('collides with a confirmed booking — one shared namespace', function () {
        hold($this->date, SessionSlot::Morning);

        expect(fn () => BlockedDate::create([
            'date' => $this->date,
            'session_slot' => SessionSlot::Morning,
            'reason' => 'Leave',
        ]))->toThrow(SlotUnavailableException::class);
    });

    it('gives the slot back when the block is deleted', function () {
        $block = BlockedDate::create(['date' => $this->date, 'session_slot' => null, 'reason' => 'Leave']);
        expect($this->availability->isAvailable($this->date, SessionSlot::Morning))->toBeFalse();

        $block->delete();

        expect($this->availability->isAvailable($this->date, SessionSlot::Morning))->toBeTrue()
            ->and(SlotHold::count())->toBe(0);
    });

    it('gives back the old slot when a block is moved', function () {
        $block = BlockedDate::create(['date' => $this->date, 'session_slot' => SessionSlot::Morning]);

        $block->update(['session_slot' => SessionSlot::Evening]);

        expect($this->availability->isAvailable($this->date, SessionSlot::Morning))->toBeTrue()
            ->and($this->availability->isAvailable($this->date, SessionSlot::Evening))->toBeFalse();
    });
});

describe('pending is tentative, not held', function () {
    it('lets two couples enquire for the same slot', function () {
        // This is the point of a soft hold: the studio decides between them.
        foreach (range(1, 2) as $i) {
            $booking = Booking::factory()->create(['status' => BookingStatus::Pending]);
            BookingDate::factory()->for($booking)->on($this->date, SessionSlot::Morning)->create();
        }

        expect(SlotHold::count())->toBe(0)
            ->and($this->availability->isAvailable($this->date, SessionSlot::Morning))->toBeTrue();
    });

    it('shows the slot as tentative with a notice', function () {
        $booking = Booking::factory()->create(['status' => BookingStatus::Pending]);
        BookingDate::factory()->for($booking)->on($this->date, SessionSlot::Morning)->create();

        $state = $this->availability->check($this->date, SessionSlot::Morning);

        expect($state->state)->toBe('tentative')
            ->and($state->bookable)->toBeTrue()
            ->and($state->reason)->not->toBeNull();
    });

    it('makes a pending full day mark every slot tentative', function () {
        $booking = Booking::factory()->create(['status' => BookingStatus::Pending]);
        BookingDate::factory()->for($booking)->on($this->date, SessionSlot::FullDay)->create();

        foreach (SessionSlot::concrete() as $slot) {
            expect($this->availability->check($this->date, $slot)->state)->toBe('tentative');
        }
    });
});

describe('releasing', function () {
    it('gives the dates back when a booking is cancelled', function () {
        $booking = hold($this->date, SessionSlot::FullDay);
        expect(SlotHold::count())->toBe(3);

        app(ReleaseBooking::class)->handle($booking, BookingStatus::Cancelled);

        expect(SlotHold::count())->toBe(0)
            ->and($this->availability->isAvailable($this->date, SessionSlot::FullDay))->toBeTrue();
    });

    it('lets a confirmed booking move to deposit_paid without colliding with itself', function () {
        $booking = hold($this->date, SessionSlot::Morning);

        $booking = app(ConfirmBooking::class)
            ->handle($booking, BookingStatus::DepositPaid);

        expect($booking->status)->toBe(BookingStatus::DepositPaid)
            ->and(SlotHold::count())->toBe(1);
    });

    it('frees the slot for another couple once cancelled', function () {
        $booking = hold($this->date, SessionSlot::Morning);
        app(ReleaseBooking::class)->handle($booking, BookingStatus::Cancelled);

        // Must not throw.
        hold($this->date, SessionSlot::Morning);

        expect(SlotHold::count())->toBe(1);
    });

    it('refuses to confirm into a status that does not hold', function () {
        $booking = Booking::factory()->create();

        expect(fn () => app(ConfirmBooking::class)
            ->handle($booking, BookingStatus::Quoted))
            ->toThrow(InvalidArgumentException::class);
    });
});

describe('the booking window', function () {
    it('rejects dates in the past', function () {
        $past = now()->subDay()->toDateString();

        expect($this->availability->isAvailable($past, SessionSlot::Morning))->toBeFalse()
            ->and($this->availability->windowReason($past))->toContain('passed');
    });

    it('rejects dates sooner than the minimum notice', function () {
        $soon = now()->addDays((int) config('booking.min_lead_days') - 1)->toDateString();

        expect($this->availability->isAvailable($soon, SessionSlot::Morning))->toBeFalse();
    });

    it('accepts the first date inside the window', function () {
        $earliest = now()->addDays((int) config('booking.min_lead_days'))->toDateString();

        expect($this->availability->isAvailable($earliest, SessionSlot::Morning))->toBeTrue();
    });

    it('rejects dates beyond the maximum horizon', function () {
        $far = now()->addMonths((int) config('booking.max_ahead_months') + 1)->toDateString();

        expect($this->availability->isAvailable($far, SessionSlot::Morning))->toBeFalse();
    });

    it('explains why an out-of-window date is unavailable', function () {
        $soon = now()->addDay()->toDateString();
        $state = $this->availability->check($soon, SessionSlot::Morning);

        // Unavailable options are disabled WITH A REASON, never silently gone.
        expect($state->bookable)->toBeFalse()
            ->and($state->reason)->not->toBeNull();
    });
});

describe('the calendar payload', function () {
    it('returns every date in the range with all four slots', function () {
        $from = now()->addMonths(6)->startOfDay();
        $to = $from->copy()->addDays(6);

        $calendar = $this->availability->calendar($from, $to);

        expect($calendar)->toHaveCount(7)
            ->and($calendar[$from->toDateString()]['slots'])->toHaveCount(4);
    });

    it('reads a whole month without one query per day', function () {
        hold($this->date, SessionSlot::Morning);

        DB::enableQueryLog();
        $this->availability->calendar(now()->addMonths(6), now()->addMonths(7));

        // Two queries: holds, and tentative enquiries. Never 30.
        expect(DB::getQueryLog())->toHaveCount(2);
    });

    it('marks out-of-window dates with a reason', function () {
        $calendar = $this->availability->calendar(now(), now()->addDays(2));
        $today = $calendar[now()->toDateString()];

        expect($today['in_window'])->toBeFalse()
            ->and($today['window_reason'])->not->toBeNull();
    });
});
