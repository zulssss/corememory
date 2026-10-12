<?php

declare(strict_types=1);

use App\Enums\SessionSlot;
use App\Livewire\BookingWizard;
use App\Models\Booking;
use App\Models\Package;
use App\Support\Settings;
use Livewire\Livewire;

/*
 * A Malay wedding is often nikah in the morning and sanding in the afternoon
 * of the same day. The wizard lets a couple pick several sessions on one day,
 * each stored as its own booking_dates row with its own venue.
 */

beforeEach(function () {
    $this->package = Package::factory()->priced(680000)->create();
    $this->date = now()->addMonths(6)->startOfDay()->toDateString();
});

function withNikahVenue(string $date)
{
    return Livewire::test(BookingWizard::class)
        ->call('selectDay', $date)
        ->call('selectSlot', SessionSlot::Morning->value)
        ->set('dates.0.venue', 'Masjid Putra')->set('dates.0.city', 'Putrajaya')->set('dates.0.state', 'W.P. Putrajaya');
}

it('holds morning and afternoon on the same day as two rows', function () {
    $c = withNikahVenue($this->date)->call('selectSlot', SessionSlot::Afternoon->value);

    $dates = $c->get('dates');
    expect($dates)->toHaveCount(2)
        ->and(array_column($dates, 'event_date'))->toBe([$this->date, $this->date])
        ->and(array_column($dates, 'session_slot'))->toBe(['morning', 'afternoon'])
        // The second session starts with the first one's venue.
        ->and($dates[1]['venue'])->toBe('Masjid Putra');
});

it('keeps the sessions of a day in the order the day happens', function () {
    $c = Livewire::test(BookingWizard::class)
        ->call('selectDay', $this->date)
        ->call('selectSlot', SessionSlot::Evening->value)
        ->call('selectSlot', SessionSlot::Morning->value);

    expect(array_column($c->get('dates'), 'session_slot'))->toBe(['morning', 'evening']);
});

it('removes a session when it is clicked again', function () {
    $c = withNikahVenue($this->date)
        ->call('selectSlot', SessionSlot::Afternoon->value)
        ->call('selectSlot', SessionSlot::Morning->value);

    expect(array_column($c->get('dates'), 'session_slot'))->toBe(['afternoon']);

    // Deselecting the last one keeps the day, with no session.
    $c->call('selectSlot', SessionSlot::Afternoon->value);
    expect($c->get('dates'))->toHaveCount(1)
        ->and($c->get('dates.0.event_date'))->toBe($this->date)
        ->and($c->get('dates.0.session_slot'))->toBeNull();
});

it('treats full day as exclusive both ways', function () {
    $c = withNikahVenue($this->date)
        ->call('selectSlot', SessionSlot::Afternoon->value)
        ->call('selectSlot', SessionSlot::FullDay->value);

    expect(array_column($c->get('dates'), 'session_slot'))->toBe(['full_day'])
        ->and($c->get('dates.0.venue'))->toBe('Masjid Putra');

    $c->call('selectSlot', SessionSlot::Evening->value);
    expect(array_column($c->get('dates'), 'session_slot'))->toBe(['evening']);
});

it('collapses the day to one undecided row when the day changes', function () {
    $other = now()->addMonths(6)->addDays(3)->startOfDay()->toDateString();

    $c = withNikahVenue($this->date)
        ->call('selectSlot', SessionSlot::Afternoon->value)
        ->call('selectDay', $other);

    expect($c->get('dates'))->toHaveCount(1)
        ->and($c->get('dates.0.event_date'))->toBe($other)
        ->and($c->get('dates.0.session_slot'))->toBeNull()
        ->and($c->get('dates.0.venue'))->toBe('Masjid Putra');
});

it('removes a whole day with all of its sessions', function () {
    $second = now()->addMonths(6)->addDays(1)->startOfDay()->toDateString();

    $c = withNikahVenue($this->date)
        ->call('selectSlot', SessionSlot::Afternoon->value)
        ->call('addDate')
        ->call('selectDay', $second)
        ->call('selectSlot', SessionSlot::Evening->value)
        ->call('removeDate', 0);

    expect(array_column($c->get('dates'), 'event_date'))->toBe([$second]);
});

it('limits a booking to four days, not four sessions', function () {
    $c = withNikahVenue($this->date)->call('selectSlot', SessionSlot::Afternoon->value);

    foreach ([1, 2, 3, 4] as $offset) {
        $c->call('addDate')
            ->call('selectDay', now()->addMonths(6)->addDays($offset)->startOfDay()->toDateString())
            ->call('selectSlot', SessionSlot::Morning->value);
    }

    expect(count($c->instance()->dayGroups()))->toBe(4)
        ->and($c->get('dates'))->toHaveCount(5);
});

it('asks for the venue of every session', function () {
    withNikahVenue($this->date)
        ->call('selectSlot', SessionSlot::Afternoon->value)
        ->set('dates.1.venue', '')
        ->call('nextStep')
        ->assertHasErrors(['dates.1.venue'])
        ->assertSet('step', BookingWizard::STEP_DATES);
});

it('books nikah and sanding on one day, each at its own venue, as two events', function () {
    withNikahVenue($this->date)
        ->call('selectSlot', SessionSlot::Afternoon->value)
        ->set('dates.1.venue', 'Dewan Seri Endon')
        ->call('nextStep')
        ->call('selectPackage', $this->package->id)
        ->call('nextStep')
        ->call('nextStep')
        ->set('partnerOneName', 'Aisyah')
        ->set('email', 'aisyah@example.test')
        ->set('phone', '012-345 6789')
        ->call('nextStep')
        ->set('terms', true)
        ->call('submit')
        ->assertHasNoErrors();

    $booking = Booking::with('dates')->sole();
    $rows = $booking->dates->sortBy('id')->values();

    expect($rows)->toHaveCount(2)
        ->and($rows->pluck('event_date')->map->toDateString()->unique()->all())->toBe([$this->date])
        ->and($rows->map(fn ($d) => [$d->session_slot->value, $d->venue])->all())
        ->toBe([['morning', 'Masjid Putra'], ['afternoon', 'Dewan Seri Endon']])
        // The deposit is per event: nikah and sanding are two.
        ->and($booking->deposit_cents->cents)->toBe(Settings::depositPerEvent()->cents * 2);
});
