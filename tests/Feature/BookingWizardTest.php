<?php

declare(strict_types=1);

use App\Actions\Bookings\ConfirmBooking;
use App\Enums\BookingStatus;
use App\Enums\SessionSlot;
use App\Livewire\BookingWizard;
use App\Models\AddOn;
use App\Models\Booking;
use App\Models\BookingDate;
use App\Models\Package;
use App\Models\SlotHold;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    $this->package = Package::factory()->priced(680000)->create(['name' => 'Wedding Classic']);
    $this->date = now()->addMonths(6)->startOfDay()->toDateString();
});

/** Drives the wizard to the review step with valid input. */
function fillWizard(Package $package, string $date, array $addOns = []): Testable
{
    $component = Livewire::test(BookingWizard::class)
        ->call('selectDay', $date)
        ->call('selectSlot', SessionSlot::FullDay->value)
        ->call('nextStep')
        ->call('selectPackage', $package->id);

    foreach ($addOns as $id => $qty) {
        $component->call('setAddOnQuantity', $id, $qty);
    }

    return $component
        ->call('nextStep')   // package -> add-ons
        ->call('nextStep')   // add-ons -> details
        ->set('partnerOneName', 'Aisyah')
        ->set('partnerTwoName', 'Danial')
        ->set('email', 'aisyah@example.test')
        ->set('phone', '012-345 6789')
        ->set('guestCount', 300)
        ->call('nextStep')   // details -> review
        ->set('terms', true);
}

describe('the order of operations', function () {
    it('opens on the date step', function () {
        Livewire::test(BookingWizard::class)
            ->assertSet('step', BookingWizard::STEP_DATES);
    });

    it('refuses to advance without a date and slot', function () {
        // A clash must surface before the couple invests any effort, which is
        // why the calendar cannot be skipped.
        Livewire::test(BookingWizard::class)
            ->call('nextStep')
            ->assertHasErrors()
            ->assertSet('step', BookingWizard::STEP_DATES);
    });

    it('refuses to advance past the package step without a package', function () {
        Livewire::test(BookingWizard::class)
            ->call('selectDay', $this->date)
            ->call('selectSlot', SessionSlot::Morning->value)
            ->call('nextStep')
            ->call('nextStep')
            ->assertHasErrors(['packageId'])
            ->assertSet('step', BookingWizard::STEP_PACKAGE);
    });

    it('will not let a couple jump forward past the calendar', function () {
        Livewire::test(BookingWizard::class)
            ->call('goToStep', BookingWizard::STEP_REVIEW)
            ->assertSet('step', BookingWizard::STEP_DATES);
    });

    it('keeps input when navigating back', function () {
        Livewire::test(BookingWizard::class)
            ->call('selectDay', $this->date)
            ->call('selectSlot', SessionSlot::Morning->value)
            ->call('nextStep')
            ->call('selectPackage', $this->package->id)
            ->call('previousStep')
            ->assertSet('step', BookingWizard::STEP_DATES)
            ->assertSet('dates.0.event_date', $this->date)
            ->assertSet('packageId', $this->package->id);
    });
});

describe('live clash detection', function () {
    it('will not advance when the chosen slot was taken meanwhile', function () {
        $component = Livewire::test(BookingWizard::class)
            ->call('selectDay', $this->date)
            ->call('selectSlot', SessionSlot::Morning->value);

        // Someone else confirms that slot while this couple is deciding.
        $other = Booking::factory()->create();
        BookingDate::factory()->for($other)->on($this->date, SessionSlot::Morning)->create();
        app(ConfirmBooking::class)->handle($other);

        $component->call('nextStep')
            ->assertSet('step', BookingWizard::STEP_DATES)
            ->assertNotSet('clashError', null);
    });

    it('rejects a stale tab at submit and sends them back to the calendar', function () {
        $component = fillWizard($this->package, $this->date);

        // The slot is confirmed by someone else after the couple reached review.
        $other = Booking::factory()->create();
        BookingDate::factory()->for($other)->on($this->date, SessionSlot::Morning)->create();
        app(ConfirmBooking::class)->handle($other);

        $component->call('submit')
            ->assertSet('step', BookingWizard::STEP_DATES)
            ->assertNotSet('clashError', null);

        // Never accepted silently.
        expect(Booking::where('email', 'aisyah@example.test')->exists())->toBeFalse();
    });

    it('still offers a slot that only has a pending enquiry', function () {
        $pending = Booking::factory()->create(['status' => BookingStatus::Pending]);
        BookingDate::factory()->for($pending)->on($this->date, SessionSlot::Morning)->create();

        Livewire::test(BookingWizard::class)
            ->call('selectDay', $this->date)
            ->call('selectSlot', SessionSlot::Morning->value)
            ->call('nextStep')
            ->assertSet('step', BookingWizard::STEP_PACKAGE);
    });
});

describe('pricing', function () {
    it('adds quantifiable add-ons at the right multiple', function () {
        $extraHour = AddOn::factory()->quantifiable(6)->priced(45000)->create();

        $component = Livewire::test(BookingWizard::class)
            ->call('selectDay', $this->date)
            ->call('selectSlot', SessionSlot::FullDay->value)
            ->call('nextStep')
            ->call('selectPackage', $this->package->id)
            ->call('setAddOnQuantity', $extraHour->id, 3);

        // 680000 + (45000 x 3) = 815000
        expect($component->get('quote')->total->cents)->toBe(815000);
    });

    it('clamps a quantity above the add-on maximum', function () {
        $travel = AddOn::factory()->quantifiable(3)->priced(120000)->create();

        $component = Livewire::test(BookingWizard::class)
            ->call('selectPackage', $this->package->id)
            ->call('setAddOnQuantity', $travel->id, 99);

        expect($component->get('addOns')[$travel->id])->toBe(3);
    });

    it('drops add-ons that are not offered with the chosen package', function () {
        $other = Package::factory()->create();
        $restricted = AddOn::factory()->onlyFor([$other->id])->create();

        $component = Livewire::test(BookingWizard::class)
            ->call('selectPackage', $other->id)
            ->call('toggleAddOn', $restricted->id)
            ->call('selectPackage', $this->package->id);

        // Otherwise the running total silently includes something the couple
        // can no longer have.
        expect($component->get('addOns'))->not->toHaveKey($restricted->id);
    });

    it('splits a deposit that adds back to the total', function () {
        $component = Livewire::test(BookingWizard::class)->call('selectPackage', $this->package->id);
        $quote = $component->get('quote');

        expect($quote->deposit->plus($quote->balance())->cents)->toBe($quote->total->cents);
    });
});

describe('submitting', function () {
    it('creates a pending booking that does not hold the date', function () {
        fillWizard($this->package, $this->date)->call('submit');

        $booking = Booking::where('email', 'aisyah@example.test')->firstOrFail();

        expect($booking->status)->toBe(BookingStatus::Pending)
            ->and($booking->reference)->toStartWith('CM-'.now()->year.'-')
            ->and($booking->dates)->toHaveCount(1)
            // A request is not a confirmation — nothing is held.
            ->and(SlotHold::count())->toBe(0);
    });

    it('captures prices at booking time', function () {
        fillWizard($this->package, $this->date)->call('submit');
        $booking = Booking::where('email', 'aisyah@example.test')->firstOrFail();

        // Raising the price later must not rewrite this enquiry.
        $this->package->update(['price_cents' => 999999]);

        expect($booking->fresh()->estimated_total_cents->cents)->toBe(680000);
    });

    it('stores a two-day booking under one reference', function () {
        $second = now()->addMonths(6)->addDay()->startOfDay()->toDateString();

        Livewire::test(BookingWizard::class)
            ->call('selectDay', $this->date)
            ->call('selectSlot', SessionSlot::Morning->value)
            ->call('addDate')
            ->call('selectDay', $second)
            ->call('selectSlot', SessionSlot::Evening->value)
            ->call('nextStep')
            ->call('selectPackage', $this->package->id)
            ->call('nextStep')->call('nextStep')
            ->set('partnerOneName', 'Farah')
            ->set('email', 'farah@example.test')
            ->set('phone', '0123456789')
            ->call('nextStep')
            ->set('terms', true)
            ->call('submit');

        $booking = Booking::where('email', 'farah@example.test')->firstOrFail();

        expect($booking->dates)->toHaveCount(2)
            ->and($booking->dates->pluck('session_slot')->map->value->all())
            ->toBe(['morning', 'evening']);
    });

    it('rejects a submission without the terms checkbox', function () {
        fillWizard($this->package, $this->date)
            ->set('terms', false)
            ->call('submit')
            ->assertHasErrors(['terms']);

        expect(Booking::where('email', 'aisyah@example.test')->exists())->toBeFalse();
    });

    it('silently drops a submission that fills the honeypot', function () {
        fillWizard($this->package, $this->date)
            ->set('website_url', 'http://spam.example')
            ->call('submit');

        expect(Booking::where('email', 'aisyah@example.test')->exists())->toBeFalse();
    });

    it('allocates sequential references', function () {
        fillWizard($this->package, $this->date)->call('submit');
        fillWizard($this->package, now()->addMonths(7)->toDateString())->call('submit');

        expect(Booking::orderBy('id')->pluck('reference')->all())
            ->toBe(['CM-'.now()->year.'-0001', 'CM-'.now()->year.'-0002']);
    });
});

describe('validation', function () {
    it('rejects a malformed email', function () {
        fillWizard($this->package, $this->date)
            ->set('email', 'not-an-email')
            ->call('submit')
            ->assertHasErrors(['email']);
    });

    it('accepts Malaysian phone numbers in every common format', function (string $phone) {
        fillWizard($this->package, $this->date)
            ->set('phone', $phone)
            ->call('submit')
            ->assertHasNoErrors(['phone']);
    })->with([
        '012-345 6789',
        '0123456789',
        '+60 12-345 6789',
        '+60123456789',
        '60123456789',
        '011-1234 5678',
    ]);

    it('rejects something that is not a phone number', function () {
        fillWizard($this->package, $this->date)
            ->set('phone', 'call me maybe')
            ->call('submit')
            ->assertHasErrors(['phone']);
    });
});
