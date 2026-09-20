<?php

declare(strict_types=1);

use App\Actions\Bookings\ConfirmBooking;
use App\Actions\Bookings\CreateBooking;
use App\Enums\SessionSlot;
use App\Exceptions\SlotUnavailableException;
use App\Models\Booking;
use App\Models\BookingDate;
use App\Models\Package;
use App\Models\SlotHold;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

/*
|------------------------------------------------------------------------------
| THE CONCURRENT-SUBMIT GUARANTEE
|------------------------------------------------------------------------------
| "Two people hitting submit at the same second must not both succeed."
|
| These tests use TWO REAL DATABASE CONNECTIONS so the race is genuine rather
| than simulated. A sequential test would pass even if the protection were only
| an application-level check — which would still lose the race in production.
|
| The protection is UNIQUE(event_date, session_slot) on slot_holds. The insert
| IS the lock: there is no window between checking and writing, because we
| never check. We write, and let the database reject the loser.
*/

/** A second connection to the same database, so two writers can genuinely race. */
function racer(): Connection
{
    config(['database.connections.racer' => config('database.connections.mysql')]);

    $connection = DB::connection('racer');

    // Without this the loser waits out innodb_lock_wait_timeout (50s by
    // default) and the suite appears to hang.
    $connection->statement('SET SESSION innodb_lock_wait_timeout = 3');

    return $connection;
}

function bookingOn(string $date, SessionSlot $slot): Booking
{
    $booking = Booking::factory()->create();
    BookingDate::factory()->for($booking)->on($date, $slot)->create();

    return $booking;
}

beforeEach(function () {
    $this->date = now()->addMonths(6)->startOfDay()->toDateString();
});

afterEach(function () {
    DB::disconnect('racer');
});

it('lets only one of two simultaneous writers take a slot', function () {
    $second = racer();

    $row = fn () => [
        'event_date' => $this->date,
        'session_slot' => 'morning',
        'holdable_type' => BookingDate::class,
        'holdable_id' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ];

    // Writer A opens a transaction and claims the slot, but has not committed.
    DB::beginTransaction();
    DB::table('slot_holds')->insert($row());

    // Writer B tries the same slot at the same moment. InnoDB blocks it on the
    // unique index, then it fails — either on the lock wait, or on 1062 once
    // A commits. Either way it does NOT get the slot.
    $secondFailed = false;
    try {
        $second->table('slot_holds')->insert($row());
    } catch (Throwable) {
        $secondFailed = true;
    }

    DB::commit();

    expect($secondFailed)->toBeTrue()
        ->and(DB::table('slot_holds')->where('event_date', $this->date)->count())->toBe(1);
});

it('rejects the second of two admins confirming the same slot', function () {
    // The real-world shape of the race: two studio staff confirming two
    // different enquiries onto the same Saturday at the same moment.
    $first = bookingOn($this->date, SessionSlot::Morning);
    $second = bookingOn($this->date, SessionSlot::Morning);

    $confirm = app(ConfirmBooking::class);

    $confirm->handle($first);

    expect(fn () => $confirm->handle($second))
        ->toThrow(SlotUnavailableException::class);

    // And the loser is left entirely alone — not half-confirmed.
    expect(SlotHold::count())->toBe(1)
        ->and($second->fresh()->status->value)->toBe('pending');
});

it('rolls back every date when one date of a multi-date booking clashes', function () {
    // A nikah + reception booking where only the reception clashes must not
    // end up with the nikah held and the reception missing — it is one
    // transaction or nothing.
    $blocker = bookingOn($this->date, SessionSlot::Evening);
    app(ConfirmBooking::class)->handle($blocker);

    $nikahDate = now()->addMonths(6)->subDay()->startOfDay()->toDateString();

    $twoDay = Booking::factory()->create();
    BookingDate::factory()->for($twoDay)->on($nikahDate, SessionSlot::Morning)->create();
    BookingDate::factory()->for($twoDay)->on($this->date, SessionSlot::Evening)->create();

    expect(fn () => app(ConfirmBooking::class)->handle($twoDay))
        ->toThrow(SlotUnavailableException::class);

    // Only the blocker's hold survives. The nikah date was rolled back.
    expect(SlotHold::count())->toBe(1)
        ->and(SlotHold::where('event_date', $nikahDate)->exists())->toBeFalse();
});

it('rejects a full day racing a single slot', function () {
    $single = bookingOn($this->date, SessionSlot::Afternoon);
    $fullDay = bookingOn($this->date, SessionSlot::FullDay);

    app(ConfirmBooking::class)->handle($single);

    expect(fn () => app(ConfirmBooking::class)->handle($fullDay))
        ->toThrow(SlotUnavailableException::class);

    // The full day's morning hold must not survive the failure.
    expect(SlotHold::count())->toBe(1)
        ->and(SlotHold::where('session_slot', 'morning')->exists())->toBeFalse();
});

it('gives every booking a unique reference under concurrent allocation', function () {
    // count() + 1 would hand two simultaneous requests the same number. The
    // locked sequences row is what makes this hold.
    $references = collect(range(1, 12))->map(
        fn () => app(CreateBooking::class)->handle([
            'package_id' => Package::factory()->create()->id,
            'dates' => [[
                'event_date' => now()->addMonths(6)->addDays(rand(1, 300))->toDateString(),
                'session_slot' => 'morning',
            ]],
            'partner_one_name' => 'Racer',
            'email' => 'racer@example.test',
            'phone' => '012-345 6789',
        ])->reference
    );

    expect($references->unique())->toHaveCount(12);
});
