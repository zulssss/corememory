<?php

declare(strict_types=1);

use App\Actions\Bookings\ConfirmBooking;
use App\Enums\BookingStatus;
use App\Enums\SessionSlot;
use App\Filament\Pages\BookingCalendar;
use App\Models\BlockedDate;
use App\Models\Booking;
use App\Models\BookingDate;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('admin');
    $this->actingAs($user);
});

it('loads the calendar page', function () {
    $this->get(BookingCalendar::getUrl())->assertOk();
});

it('shows a confirmed booking on its date and slot', function () {
    $date = now()->addMonths(6)->startOfDay();
    $booking = Booking::factory()->create(['partner_one_name' => 'Aisyah', 'partner_two_name' => 'Danial']);
    BookingDate::factory()->for($booking)->on($date->toDateString(), SessionSlot::Morning)->create();
    app(ConfirmBooking::class)->handle($booking);

    Livewire\Livewire::test(BookingCalendar::class)
        ->set('month', $date->format('Y-m'))
        ->assertSee('Aisyah & Danial');
});

it('distinguishes a block from a booking', function () {
    $date = now()->addMonths(6)->startOfDay();
    BlockedDate::create(['date' => $date, 'session_slot' => SessionSlot::Morning, 'reason' => 'Studio leave']);

    $weeks = Livewire\Livewire::test(BookingCalendar::class)
        ->set('month', $date->format('Y-m'))
        ->get('weeks');

    expect($weeks[$date->toDateString()]['slots']['morning']['state'])->toBe('blocked')
        ->and($weeks[$date->toDateString()]['slots']['morning']['label'])->toBe('Studio leave');
});

it('marks a pending enquiry as tentative, not booked', function () {
    $date = now()->addMonths(6)->startOfDay();
    $booking = Booking::factory()->create(['status' => BookingStatus::Pending]);
    BookingDate::factory()->for($booking)->on($date->toDateString(), SessionSlot::Evening)->create();

    $weeks = Livewire\Livewire::test(BookingCalendar::class)
        ->set('month', $date->format('Y-m'))
        ->get('weeks');

    expect($weeks[$date->toDateString()]['slots']['evening']['state'])->toBe('tentative');
});

it('reads a whole month without one query per day', function () {
    $date = now()->addMonths(6)->startOfDay();

    DB::enableQueryLog();
    Livewire\Livewire::test(BookingCalendar::class)->set('month', $date->format('Y-m'))->get('weeks');

    // Two queries for the grid: holds, and tentative enquiries. Eager-loading
    // adds a few more for the relations, but it must never scale with days.
    expect(count(DB::getQueryLog()))->toBeLessThan(10);
});
