<?php

declare(strict_types=1);

use App\Actions\Bookings\ConfirmBooking;
use App\Enums\BookingStatus;
use App\Enums\PackageCategory;
use App\Enums\SessionSlot;
use App\Livewire\BookingWizard;
use App\Models\AddOn;
use App\Models\Booking;
use App\Models\BookingDate;
use App\Models\Package;
use App\Models\SlotHold;
use App\Services\AvailabilityService;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    $this->package = Package::factory()->priced(680000)->create(['name' => 'Wedding Classic']);
    $this->date = now()->addMonths(6)->startOfDay()->toDateString();
});

/** The wizard step key currently rendered, for the morph-identity tests. */
function morphKey(string $html): ?string
{
    preg_match('/wire:key="(wizard-step-[a-z-]+)"/', $html, $m);

    return $m[1] ?? null;
}

/** Straight to the package step. */
function onPackageStep(): Testable
{
    return Livewire::test(BookingWizard::class)
        ->set('step', BookingWizard::STEP_PACKAGE);
}

/** Drives the wizard to the review step with valid input. */
function fillWizard(Package $package, string $date, array $addOns = []): Testable
{
    $component = Livewire::test(BookingWizard::class)
        ->call('selectDay', $date)
        ->call('selectSlot', SessionSlot::FullDay->value)
        ->set('dates.0.venue', 'Dewan Seri Endon')->set('dates.0.city', 'Putrajaya')->set('dates.0.state', 'W.P. Putrajaya')
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
            ->set('dates.0.venue', 'Dewan Seri Endon')->set('dates.0.city', 'Putrajaya')->set('dates.0.state', 'W.P. Putrajaya')
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
            ->set('dates.0.venue', 'Dewan Seri Endon')->set('dates.0.city', 'Putrajaya')->set('dates.0.state', 'W.P. Putrajaya')
            ->call('nextStep')
            ->call('selectPackage', $this->package->id)
            ->call('previousStep')
            ->assertSet('step', BookingWizard::STEP_DATES)
            ->assertSet('dates.0.event_date', $this->date)
            ->assertSet('packageId', $this->package->id);
    });
});

describe('the calendar step', function () {
    /*
     * These render the slot list, which nothing else in this file does: every
     * other test chooses a date six months out, while the wizard opens on the
     * month containing earliestDate(). selectDay() does not move the calendar,
     * so `$this->calendar[$chosenDay]` misses and the slot loop is a no-op.
     * The markup shipped broken because of it.
     */
    beforeEach(function () {
        $this->visibleDate = app(AvailabilityService::class)->earliestDate()->toDateString();
    });

    it('renders every session option for the chosen day', function () {
        Livewire::test(BookingWizard::class)
            ->call('selectDay', $this->visibleDate)
            ->assertSee(SessionSlot::Morning->labelWithTime())
            ->assertSee(SessionSlot::Afternoon->labelWithTime())
            ->assertSee(SessionSlot::Evening->labelWithTime())
            ->assertSee(SessionSlot::FullDay->labelWithTime());
    });

    it('keeps an unavailable session visible with its reason', function () {
        // The brief is explicit: unavailable options are disabled and explained,
        // never silently removed.
        $other = Booking::factory()->create();
        BookingDate::factory()->for($other)->on($this->visibleDate, SessionSlot::Morning)->create();
        app(ConfirmBooking::class)->handle($other);

        Livewire::test(BookingWizard::class)
            ->call('selectDay', $this->visibleDate)
            ->assertSee(SessionSlot::Morning->labelWithTime())
            ->assertSee(__('booking.availability.reason_booked'))
            // Full day is blocked too, because morning is one of the slots it consumes.
            ->assertSee(__('booking.availability.reason_full_day_partly_taken'));
    });
});

describe('the phone number', function () {
    it('formats ten typed digits', function () {
        Livewire::test(BookingWizard::class)
            ->set('phone', '0124522344')
            ->assertSet('phone', '012-452 2344')
            ->assertHasNoErrors('phone');
    });

    it('accepts a number containing an invisible non-breaking hyphen', function () {
        // macOS/iOS smart punctuation swaps in U+2011; it looks identical to a
        // normal hyphen and used to fail validation.
        fillWizard($this->package, $this->date)
            ->set('phone', "012\u{2011}452 2344")
            ->call('submit')
            ->assertHasNoErrors();

        expect(Booking::where('email', 'aisyah@example.test')->sole()->phone)->toBe('012-452 2344');
    });

    it('marks the input for as-you-type formatting', function () {
        Livewire::test(BookingWizard::class)
            ->set('step', BookingWizard::STEP_DETAILS)
            ->assertSeeHtml('data-phone-format');
    });
});

describe('optional fields left blank', function () {
    /*
     * Regression: "How did you find us?" defaults to an option with value="".
     * Once touched, Livewire stores '' rather than null; CreateBooking passed it
     * through with `?? null`, which only replaces null, and the EnquirySource
     * cast threw a 500 on "Send booking request".
     */
    it('accepts a touched-but-empty source', function () {
        fillWizard($this->package, $this->date)
            ->set('source', '')
            ->call('submit')
            ->assertHasNoErrors();

        expect(Booking::where('email', 'aisyah@example.test')->sole()->source)->toBeNull();
    });

    it('accepts a blank guest count', function () {
        fillWizard($this->package, $this->date)
            ->set('guestCount', null)
            ->call('submit')
            ->assertHasNoErrors();

        expect(Booking::where('email', 'aisyah@example.test')->sole()->guest_count)->toBeNull();
    });
});

describe('a complete date', function () {
    it('warns when Continue is pressed without a session', function () {
        Livewire::test(BookingWizard::class)
            ->call('selectDay', $this->date)
            ->call('nextStep')
            ->assertSet('step', BookingWizard::STEP_DATES)
            ->assertHasErrors(['dates.0.session_slot'])
            ->assertSee(__('booking.errors.session_required'))
            ->assertDispatched('wizard-reveal', target: 'error');
    });

    it('warns for each missing venue detail, without asterisks', function () {
        $c = Livewire::test(BookingWizard::class)
            ->call('selectDay', $this->date)
            ->call('selectSlot', SessionSlot::Morning->value)
            ->call('nextStep')
            ->assertSet('step', BookingWizard::STEP_DATES)
            ->assertHasErrors(['dates.0.venue', 'dates.0.city', 'dates.0.state'])
            ->assertSee(__('booking.errors.venue_required'))
            ->assertSee(__('booking.errors.city_required'))
            ->assertSee(__('booking.errors.state_required'));

        expect($c->html())->not->toContain('*');
    });

    it('clears a warning as soon as the field is filled', function () {
        Livewire::test(BookingWizard::class)
            ->call('selectDay', $this->date)
            ->call('selectSlot', SessionSlot::Morning->value)
            ->call('nextStep')
            ->assertHasErrors(['dates.0.venue'])
            ->set('dates.0.venue', 'Dewan Seri Endon')
            ->assertHasNoErrors(['dates.0.venue'])
            ->assertHasErrors(['dates.0.city']);
    });

    it('opens the incomplete date when there are several', function () {
        // The second date is the one missing its venue; it must be put on
        // screen, otherwise its warnings are invisible.
        $second = now()->addMonths(6)->addDays(1)->startOfDay()->toDateString();

        Livewire::test(BookingWizard::class)
            ->call('selectDay', $this->date)
            ->call('selectSlot', SessionSlot::Morning->value)
            ->set('dates.0.venue', 'Dewan Seri Endon')->set('dates.0.city', 'Putrajaya')->set('dates.0.state', 'W.P. Putrajaya')
            ->call('addDate')
            ->call('selectDay', $second)
            ->call('selectSlot', SessionSlot::Evening->value)
            ->call('focusDate', 0)
            ->call('nextStep')
            ->assertSet('activeDate', 1)
            ->assertHasErrors(['dates.1.venue']);
    });

    it('refuses a booking posted straight at submit without a venue', function () {
        // The step checks guide the couple; submit is the guarantee.
        $c = Livewire::test(BookingWizard::class)
            ->call('selectDay', $this->date)
            ->call('selectSlot', SessionSlot::FullDay->value)
            ->set('packageId', $this->package->id)
            ->set('partnerOneName', 'Aisyah')
            ->set('email', 'aisyah@example.test')
            ->set('phone', '012-345 6789')
            ->set('terms', true)
            ->call('submit')
            ->assertHasErrors(['dates.0.venue']);

        expect(Booking::count())->toBe(0);
    });
});

describe('guiding the eye within a step', function () {
    it('brings the sessions into view after a day is picked', function () {
        Livewire::test(BookingWizard::class)
            ->call('selectDay', $this->date)
            ->assertDispatched('wizard-reveal', target: 'sessions');
    });

    it('brings the venue fields into view after a session is picked', function () {
        Livewire::test(BookingWizard::class)
            ->call('selectDay', $this->date)
            ->call('selectSlot', SessionSlot::Morning->value)
            ->assertDispatched('wizard-reveal', target: 'venue');
    });

    it('brings Continue into view after a package is picked', function () {
        Livewire::test(BookingWizard::class)
            ->call('selectPackage', $this->package->id)
            ->assertDispatched('wizard-reveal', target: 'continue');
    });

    it('marks every anchor the browser scrolls to', function () {
        $html = Livewire::test(BookingWizard::class)
            ->call('selectDay', $this->date)
            ->call('selectSlot', SessionSlot::Morning->value)
            ->html();

        foreach (['sessions', 'venue', 'continue'] as $section) {
            expect($html)->toContain("data-wizard-section=\"{$section}\"");
        }
    });
});

describe('the progress bar', function () {
    it('marks the current step and lets the couple go back', function () {
        $html = Livewire::test(BookingWizard::class)
            ->call('selectDay', $this->date)
            ->call('selectSlot', SessionSlot::Morning->value)
            ->set('dates.0.venue', 'Dewan Seri Endon')->set('dates.0.city', 'Putrajaya')->set('dates.0.state', 'W.P. Putrajaya')
            ->call('nextStep')
            ->call('selectPackage', $this->package->id)
            ->call('nextStep')
            ->html();

        // Step 3 current, 1-2 done and clickable, 4-5 upcoming and disabled.
        preg_match_all('/<button[^>]*wire:click="goToStep\((\d)\)"[^>]*>/', $html, $buttons, PREG_SET_ORDER);
        $state = [];
        foreach ($buttons as [$tag, $n]) {
            $state[(int) $n] = match (true) {
                str_contains($tag, 'aria-current="step"') => 'current',
                str_contains($tag, 'disabled') => 'disabled',
                default => 'clickable',
            };
        }

        expect($state)->toBe([1 => 'clickable', 2 => 'clickable', 3 => 'current', 4 => 'disabled', 5 => 'disabled']);
    });
});

describe('the package step, one category at a time', function () {
    beforeEach(function () {
        // $this->package (Wedding Classic) is a Photo package via the factory.
        $this->video = Package::factory()->priced(140000)->create([
            'name' => 'Video Basic — Single Event',
            'category' => PackageCategory::Video,
        ]);
    });

    it('shows a tab for each category that has packages', function () {
        onPackageStep()
            ->assertSeeHtml('wire:click="showCategory(\'photo\')"')
            ->assertSeeHtml('wire:click="showCategory(\'video\')"')
            // No Sessions packages exist in this test, so no empty tab.
            ->assertDontSeeHtml('wire:click="showCategory(\'session\')"');
    });

    it('opens on the first category when nothing is chosen', function () {
        onPackageStep()
            ->assertSet('packageCategory', 'photo')
            ->assertSeeHtml('wire:click="selectPackage('.$this->package->id.')"')
            ->assertDontSeeHtml('wire:click="selectPackage('.$this->video->id.')"');
    });

    it('opens on the tab holding a package chosen from /packages', function () {
        Livewire::test(BookingWizard::class, ['package' => $this->video->slug])
            ->assertSet('packageCategory', 'video');
    });

    it('switches tabs and shows only that category', function () {
        onPackageStep()
            ->call('showCategory', 'video')
            ->assertSet('packageCategory', 'video')
            ->assertSeeHtml('wire:click="selectPackage('.$this->video->id.')"')
            ->assertDontSeeHtml('wire:click="selectPackage('.$this->package->id.')"');
    });

    it('ignores a category that does not exist', function () {
        onPackageStep()
            ->call('showCategory', 'nonsense')
            ->assertSet('packageCategory', 'photo');
    });

    it('highlights the chosen package in the comparison', function () {
        $html = onPackageStep()
            ->call('selectPackage', $this->package->id)
            ->html();

        expect($html)->toContain('aria-pressed="true"')
            ->and($html)->toContain('● '.__('booking.wizard.selected'))
            ->and($html)->toContain('bg-paper-raised');
    });

    it('marks the tab holding the choice while another tab is open', function () {
        $html = onPackageStep()
            ->call('selectPackage', $this->package->id)
            ->call('showCategory', 'video')
            ->html();

        // The Photo tab carries the dot; the choice has not been lost.
        // Isolate each tab's markup (Livewire inserts block comments inside).
        preg_match('/showCategory\(\'photo\'\).*?<\/button>/s', $html, $photo);
        preg_match('/showCategory\(\'video\'\).*?<\/button>/s', $html, $video);

        expect($photo[0])->toContain('●')
            ->and($video[0])->not->toContain('●');
    });

    it('keys the comparison per category so switching tabs cannot mis-bind', function () {
        onPackageStep()
            ->assertSeeHtml('wire:key="package-compare-photo"')
            ->call('showCategory', 'video')
            ->assertSeeHtml('wire:key="package-compare-video"')
            ->assertDontSeeHtml('wire:key="package-compare-photo"');
    });
});

describe('the package step', function () {
    it('labels the selected package, not just its border', function () {
        Livewire::test(BookingWizard::class)
            ->call('selectDay', $this->date)
            ->call('selectSlot', SessionSlot::Morning->value)
            ->set('dates.0.venue', 'Dewan Seri Endon')->set('dates.0.city', 'Putrajaya')->set('dates.0.state', 'W.P. Putrajaya')
            ->call('nextStep')
            ->call('selectPackage', $this->package->id)
            ->assertSee(__('booking.wizard.selected'))
            ->assertSeeHtml('aria-pressed="true"');
    });
});

describe('moving between steps', function () {
    /*
     * The Continue button sits at the foot of each step and Livewire keeps the
     * scroll position across a morph, so without this the couple would land at
     * the bottom of the next step. The browser listens for this event and
     * brings the new step into view (motion.js scrollToWizardTop).
     */
    it('announces a step change when Continue succeeds', function () {
        Livewire::test(BookingWizard::class)
            ->call('selectDay', $this->date)
            ->call('selectSlot', SessionSlot::Morning->value)
            ->set('dates.0.venue', 'Dewan Seri Endon')->set('dates.0.city', 'Putrajaya')->set('dates.0.state', 'W.P. Putrajaya')
            ->call('nextStep')
            ->assertSet('step', BookingWizard::STEP_PACKAGE)
            ->assertDispatched('wizard-step-changed', step: BookingWizard::STEP_PACKAGE);
    });

    it('stays put when Continue fails validation', function () {
        // The couple must stay on the error they need to fix, not be scrolled
        // away from it.
        Livewire::test(BookingWizard::class)
            ->call('nextStep')
            ->assertSet('step', BookingWizard::STEP_DATES)
            ->assertNotDispatched('wizard-step-changed');
    });

    it('announces going back', function () {
        Livewire::test(BookingWizard::class)
            ->call('selectDay', $this->date)
            ->call('selectSlot', SessionSlot::Morning->value)
            ->set('dates.0.venue', 'Dewan Seri Endon')->set('dates.0.city', 'Putrajaya')->set('dates.0.state', 'W.P. Putrajaya')
            ->call('nextStep')
            ->call('previousStep')
            ->assertSet('step', BookingWizard::STEP_DATES)
            ->assertDispatched('wizard-step-changed', step: BookingWizard::STEP_DATES);
    });

    it('announces a jump from the progress bar', function () {
        Livewire::test(BookingWizard::class)
            ->call('selectDay', $this->date)
            ->call('selectSlot', SessionSlot::Morning->value)
            ->set('dates.0.venue', 'Dewan Seri Endon')->set('dates.0.city', 'Putrajaya')->set('dates.0.state', 'W.P. Putrajaya')
            ->call('nextStep')
            ->call('selectPackage', $this->package->id)
            ->call('nextStep')
            ->call('goToStep', BookingWizard::STEP_DATES)
            ->assertDispatched('wizard-step-changed', step: BookingWizard::STEP_DATES);
    });

    it('does not scroll on actions that stay on the same step', function () {
        // Picking a day re-renders the calendar but is not a step change.
        Livewire::test(BookingWizard::class)
            ->call('selectDay', $this->date)
            ->assertNotDispatched('wizard-step-changed');
    });

    it('does not scroll on the first page load', function () {
        Livewire::test(BookingWizard::class)
            ->assertNotDispatched('wizard-step-changed');
    });
});

describe('the wizard survives a DOM morph', function () {
    /*
     * "Next" and "Send booking request" occupy the same position and are both
     * <button type="button">. Without distinct wire:key values Livewire's morph
     * reuses one element for the other and only patches wire:click — and the
     * click binding does not survive that patch. The button then renders
     * perfectly and does nothing: no submit, no terms warning, no error at all.
     * Reloading the page rebuilt the element, which is why refreshing "fixed"
     * it. Same hazard across the five step blocks, whose inputs sit at matching
     * positions.
     */
    it('gives the next and submit buttons different morph keys', function () {
        $onDetails = Livewire::test(BookingWizard::class)
            ->call('selectDay', $this->date)
            ->call('selectSlot', SessionSlot::Morning->value)
            ->set('dates.0.venue', 'Dewan Seri Endon')->set('dates.0.city', 'Putrajaya')->set('dates.0.state', 'W.P. Putrajaya')
            ->call('nextStep')
            ->call('selectPackage', $this->package->id)
            ->call('nextStep')
            ->call('nextStep');

        expect($onDetails->html())
            ->toContain('wire:key="wizard-nav-next"')
            ->not->toContain('wire:key="wizard-nav-submit"');

        $onReview = $onDetails
            ->set('partnerOneName', 'Aisyah')
            ->set('email', 'aisyah@example.test')
            ->set('phone', '012-345 6789')
            ->call('nextStep');

        expect($onReview->html())
            ->toContain('wire:key="wizard-nav-submit"')
            ->not->toContain('wire:key="wizard-nav-next"');
    });

    it('keys every step block so one step cannot inherit another\'s elements', function () {
        $keys = [];

        $c = Livewire::test(BookingWizard::class);
        $keys[] = morphKey($c->html());

        $c->call('selectDay', $this->date)->call('selectSlot', SessionSlot::Morning->value)
            ->set('dates.0.venue', 'Dewan Seri Endon')->set('dates.0.city', 'Putrajaya')->set('dates.0.state', 'W.P. Putrajaya')->call('nextStep');
        $keys[] = morphKey($c->html());

        $c->call('selectPackage', $this->package->id)->call('nextStep');
        $keys[] = morphKey($c->html());

        $c->call('nextStep');
        $keys[] = morphKey($c->html());

        $c->set('partnerOneName', 'Aisyah')->set('email', 'a@example.test')
            ->set('phone', '012-345 6789')->call('nextStep');
        $keys[] = morphKey($c->html());

        // Five steps, five distinct keys, none missing.
        expect($keys)->toHaveCount(5)
            ->and(array_filter($keys))->toHaveCount(5)
            ->and(array_unique($keys))->toHaveCount(5);
    });
});

describe('the submit actually lands the couple somewhere', function () {
    /*
     * Regression: submit() created the booking and returned a redirect, but
     * it used navigate: true and the browser silently ignored it. The couple
     * saw no change and clicked again — five identical bookings in four
     * seconds. Nothing asserted the redirect, so the suite stayed green.
     */
    it('redirects to the thank-you page with a full page load', function () {
        $component = fillWizard($this->package, $this->date)->call('submit');

        $booking = Booking::where('email', 'aisyah@example.test')->sole();

        $component->assertRedirect(route('book.thanks', ['reference' => $booking->reference]));

        // navigate: true is what broke. If it ever comes back, fail here.
        expect($component->effects['redirectUsingNavigate'] ?? false)->toBeFalse();
    });

    it('serves the page it redirects to', function () {
        fillWizard($this->package, $this->date)->call('submit');

        $booking = Booking::where('email', 'aisyah@example.test')->sole();

        $this->get(route('book.thanks', ['reference' => $booking->reference]))
            ->assertOk()
            ->assertSee($booking->reference);
    });
});

describe('live clash detection', function () {
    it('will not advance when the chosen slot was taken meanwhile', function () {
        $component = Livewire::test(BookingWizard::class)
            ->call('selectDay', $this->date)
            ->call('selectSlot', SessionSlot::Morning->value)
            ->set('dates.0.venue', 'Dewan Seri Endon')->set('dates.0.city', 'Putrajaya')->set('dates.0.state', 'W.P. Putrajaya');

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
            ->set('dates.0.venue', 'Dewan Seri Endon')->set('dates.0.city', 'Putrajaya')->set('dates.0.state', 'W.P. Putrajaya')
            ->call('nextStep')
            ->assertSet('step', BookingWizard::STEP_PACKAGE);
    });
});

describe('the deposit', function () {
    /*
     * The studio's published terms price the deposit as a FLAT amount per
     * event — "RM100 - RM200 deposit per event to lock the date" — not as a
     * percentage of the package. A solemnisation plus a reception is two
     * deposits, and a RM6,000 package carries the same deposit as a RM900 one.
     */
    it('charges a flat amount per event, not a percentage', function () {
        $component = Livewire::test(BookingWizard::class)
            ->call('selectDay', $this->date)
            ->call('selectSlot', SessionSlot::Morning->value)
            ->set('dates.0.venue', 'Dewan Seri Endon')->set('dates.0.city', 'Putrajaya')->set('dates.0.state', 'W.P. Putrajaya')
            ->call('nextStep')
            ->call('selectPackage', $this->package->id);

        $quote = $component->get('quote');

        expect($quote->total->cents)->toBe(680000)
            ->and($quote->deposit->cents)->toBe(10000)
            ->and($quote->eventCount)->toBe(1);
    });

    it('charges twice for a two-event booking', function () {
        $second = now()->addMonths(6)->addDays(1)->startOfDay()->toDateString();

        $component = Livewire::test(BookingWizard::class)
            ->call('selectDay', $this->date)
            ->call('selectSlot', SessionSlot::Morning->value)
            ->set('dates.0.venue', 'Dewan Seri Endon')->set('dates.0.city', 'Putrajaya')->set('dates.0.state', 'W.P. Putrajaya')
            ->call('addDate')
            ->call('selectDay', $second)
            ->call('selectSlot', SessionSlot::Evening->value)
            ->set('dates.1.venue', 'Dewan Seri Endon')->set('dates.1.city', 'Putrajaya')->set('dates.1.state', 'W.P. Putrajaya')
            ->call('nextStep')
            ->call('selectPackage', $this->package->id);

        $quote = $component->get('quote');

        expect($quote->eventCount)->toBe(2)
            ->and($quote->deposit->cents)->toBe(20000);
    });

    it('never quotes a deposit larger than the job itself', function () {
        // A cheap session shoot with a multi-event booking would otherwise
        // produce a negative balance, and the deposit and final invoices
        // would stop reconciling to the contract value.
        $cheap = Package::factory()->priced(9000)->create(['name' => 'Tiny']);
        $second = now()->addMonths(6)->addDays(1)->startOfDay()->toDateString();

        $quote = Livewire::test(BookingWizard::class)
            ->call('selectDay', $this->date)
            ->call('selectSlot', SessionSlot::Morning->value)
            ->set('dates.0.venue', 'Dewan Seri Endon')->set('dates.0.city', 'Putrajaya')->set('dates.0.state', 'W.P. Putrajaya')
            ->call('addDate')
            ->call('selectDay', $second)
            ->call('selectSlot', SessionSlot::Evening->value)
            ->set('dates.1.venue', 'Dewan Seri Endon')->set('dates.1.city', 'Putrajaya')->set('dates.1.state', 'W.P. Putrajaya')
            ->call('nextStep')
            ->call('selectPackage', $cheap->id)
            ->get('quote');

        expect($quote->deposit->cents)->toBe(9000)
            ->and($quote->balance()->cents)->toBe(0);
    });

    it('always reconciles: deposit plus balance equals the total', function () {
        $quote = Livewire::test(BookingWizard::class)
            ->call('selectDay', $this->date)
            ->call('selectSlot', SessionSlot::FullDay->value)
            ->set('dates.0.venue', 'Dewan Seri Endon')->set('dates.0.city', 'Putrajaya')->set('dates.0.state', 'W.P. Putrajaya')
            ->call('nextStep')
            ->call('selectPackage', $this->package->id)
            ->get('quote');

        expect($quote->deposit->plus($quote->balance())->cents)->toBe($quote->total->cents);
    });

    it('stores the same deposit on the booking that the couple was shown', function () {
        $component = fillWizard($this->package, $this->date);
        $shown = $component->get('quote')->deposit->cents;

        $component->call('submit');

        $booking = Booking::where('email', 'aisyah@example.test')->sole();

        expect($booking->deposit_cents->cents)->toBe($shown)
            ->and($booking->deposit_cents->cents)->toBe(10000);
    });
});

describe('pricing', function () {
    it('adds quantifiable add-ons at the right multiple', function () {
        $extraHour = AddOn::factory()->quantifiable(6)->priced(45000)->create();

        $component = Livewire::test(BookingWizard::class)
            ->call('selectDay', $this->date)
            ->call('selectSlot', SessionSlot::FullDay->value)
            ->set('dates.0.venue', 'Dewan Seri Endon')->set('dates.0.city', 'Putrajaya')->set('dates.0.state', 'W.P. Putrajaya')
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
            ->set('dates.0.venue', 'Dewan Seri Endon')->set('dates.0.city', 'Putrajaya')->set('dates.0.state', 'W.P. Putrajaya')
            ->call('addDate')
            ->call('selectDay', $second)
            ->call('selectSlot', SessionSlot::Evening->value)
            ->set('dates.1.venue', 'Dewan Seri Endon')->set('dates.1.city', 'Putrajaya')->set('dates.1.state', 'W.P. Putrajaya')
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
