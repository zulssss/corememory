<?php

declare(strict_types=1);

use App\Actions\Bookings\SendBookingNotifications;
use App\Mail\BookingReceivedMail;
use App\Mail\BookingSubmittedMail;
use App\Models\AddOn;
use App\Models\Booking;
use App\Models\BookingDate;
use App\Models\Package;
use App\Support\Settings;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();

    $this->booking = Booking::factory()->for(Package::factory())->create([
        'email' => 'couple@example.test',
    ]);
    BookingDate::factory()->for($this->booking)->create();
});

it('emails the couple and the studio', function () {
    Settings::set('contact.email', 'studio@example.test');

    app(SendBookingNotifications::class)->handle($this->booking);

    Mail::assertQueued(BookingReceivedMail::class,
        fn ($mail) => $mail->hasTo('couple@example.test'));

    Mail::assertQueued(BookingSubmittedMail::class,
        fn ($mail) => $mail->hasTo('studio@example.test'));
});

it('queues rather than sending, so the request never waits on SMTP', function () {
    app(SendBookingNotifications::class)->handle($this->booking);

    Mail::assertNothingSent();
    Mail::assertQueued(BookingReceivedMail::class);
});

it('renders the client email with the reference and the disclaimer', function () {
    $addOn = AddOn::factory()->quantifiable(6)->priced(45000)->create(['name' => 'Extra hour']);
    $this->booking->addOns()->attach($addOn->id, [
        'qty' => 3, 'price_cents_at_booking' => 45000, 'line_total_cents' => 135000,
    ]);

    $rendered = (new BookingReceivedMail($this->booking->fresh(['dates', 'addOns', 'package'])))->render();

    expect($rendered)
        ->toContain($this->booking->reference)
        // REQUIRED: the disclaimer must appear in the confirmation email.
        ->toContain('subject to confirmation')
        // And it must be clear this is not a confirmation.
        ->toContain('not a confirmation')
        ->toContain('Extra hour');
});

it('renders the studio email with a direct link to the admin record', function () {
    $rendered = (new BookingSubmittedMail($this->booking->fresh(['dates', 'addOns', 'package'])))->render();

    expect($rendered)
        ->toContain('/admin/bookings/'.$this->booking->id.'/edit')
        ->toContain($this->booking->email);
});

it('does not lose a booking when mail fails', function () {
    // The booking is already safely in the database. A mail outage must not
    // throw it away.
    Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP down'));

    app(SendBookingNotifications::class)->handle($this->booking);

    expect(Booking::find($this->booking->id))->not->toBeNull();
});
