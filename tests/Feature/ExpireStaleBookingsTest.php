<?php

declare(strict_types=1);

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingDate;

it('cancels a pending enquiry past the lapse window', function () {
    $stale = Booking::factory()->stale()->create();
    BookingDate::factory()->for($stale)->create();

    $this->artisan('bookings:expire-stale')->assertSuccessful();

    expect($stale->fresh()->status)->toBe(BookingStatus::Cancelled)
        ->and($stale->fresh()->lapsed_at)->not->toBeNull();
});

it('leaves a recent enquiry alone', function () {
    $fresh = Booking::factory()->create(['created_at' => now()->subDay()]);

    $this->artisan('bookings:expire-stale')->assertSuccessful();

    expect($fresh->fresh()->status)->toBe(BookingStatus::Pending);
});

it('never touches a confirmed booking, however old', function () {
    // Confirming an old enquiry and then having the scheduler cancel it would
    // release a real wedding's date.
    $confirmed = Booking::factory()->confirmed()->create(['created_at' => now()->subYear()]);

    $this->artisan('bookings:expire-stale')->assertSuccessful();

    expect($confirmed->fresh()->status)->toBe(BookingStatus::Confirmed);
});

it('changes nothing on a dry run', function () {
    $stale = Booking::factory()->stale()->create();

    $this->artisan('bookings:expire-stale', ['--dry-run' => true])->assertSuccessful();

    expect($stale->fresh()->status)->toBe(BookingStatus::Pending);
});

it('keeps the record so the conversion funnel stays accurate', function () {
    $stale = Booking::factory()->stale()->create();

    $this->artisan('bookings:expire-stale');

    // Cancelled, not deleted.
    expect(Booking::find($stale->id))->not->toBeNull();
});
