<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Actions\Bookings\CalculateQuote;
use App\Actions\Bookings\CreateBooking;
use App\Actions\Bookings\SendBookingNotifications;
use App\Enums\EnquirySource;
use App\Enums\SessionSlot;
use App\Exceptions\SlotUnavailableException;
use App\Http\Requests\BookingRules;
use App\Models\AddOn;
use App\Models\Booking;
use App\Models\Package;
use App\Services\AvailabilityService;
use App\ValueObjects\Quote;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The booking wizard.
 *
 * ORDER OF OPERATIONS IS MANDATORY: the date and session slot are chosen
 * FIRST, before the package, the add-ons or any personal details. A clash has
 * to surface before the couple has invested effort, not after. Do not move the
 * calendar later in the flow.
 *
 * State lives in the SESSION, not just in the Livewire component. A couple
 * comparing three studios at midnight will switch tabs, refresh, and come
 * back — losing their progress at that point loses the booking.
 */
#[Layout('components.layouts.app')]
#[Title('Book your date')]
class BookingWizard extends Component
{
    private const SESSION_KEY = 'booking.wizard';

    public const STEP_DATES = 1;

    public const STEP_PACKAGE = 2;

    public const STEP_ADDONS = 3;

    public const STEP_DETAILS = 4;

    public const STEP_REVIEW = 5;

    public int $step = self::STEP_DATES;

    /** @var list<array{event_date: string|null, session_slot: string|null, label: string|null, venue: string|null, city: string|null, state: string|null}> */
    public array $dates = [];

    public ?int $packageId = null;

    /** @var array<int, int> add_on_id => qty */
    public array $addOns = [];

    public string $partnerOneName = '';

    public string $partnerTwoName = '';

    public string $email = '';

    public string $phone = '';

    public ?int $guestCount = null;

    public ?string $source = null;

    public string $notes = '';

    public bool $terms = false;

    /** Honeypot. Hidden from real users; a bot fills it and is rejected. */
    public string $website_url = '';

    /** The month the calendar is showing, as Y-m. */
    public string $calendarMonth = '';

    /** Index of the date row whose slot picker is open. */
    public int $activeDate = 0;

    public ?string $reference = null;

    public ?string $clashError = null;

    public function mount(?string $package = null): void
    {
        $this->restore();

        if ($this->dates === []) {
            $this->dates = [$this->emptyDate()];
        }

        if ($this->calendarMonth === '') {
            $this->calendarMonth = app(AvailabilityService::class)->earliestDate()->format('Y-m');
        }

        // /packages links straight here with a chosen tier. The date step still
        // comes first — this only pre-selects what they already clicked.
        if ($package !== null && $this->packageId === null) {
            $this->packageId = Package::active()->where('slug', $package)->value('id');
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Session persistence
    |--------------------------------------------------------------------------
    */

    private function persist(): void
    {
        session()->put(self::SESSION_KEY, [
            'step' => $this->step,
            'dates' => $this->dates,
            'packageId' => $this->packageId,
            'addOns' => $this->addOns,
            'partnerOneName' => $this->partnerOneName,
            'partnerTwoName' => $this->partnerTwoName,
            'email' => $this->email,
            'phone' => $this->phone,
            'guestCount' => $this->guestCount,
            'source' => $this->source,
            'notes' => $this->notes,
            'calendarMonth' => $this->calendarMonth,
        ]);
    }

    private function restore(): void
    {
        foreach (session(self::SESSION_KEY, []) as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }
    }

    private function forget(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    /** Anything the couple changes is written straight back to the session. */
    public function updated(): void
    {
        $this->persist();
    }

    /*
    |--------------------------------------------------------------------------
    | Step 1 — dates and slots
    |--------------------------------------------------------------------------
    */

    /** @return array<string, array{date: string, in_window: bool, window_reason: string|null, slots: array}> */
    public function getCalendarProperty(): array
    {
        $month = CarbonImmutable::createFromFormat('Y-m', $this->calendarMonth)->startOfMonth();

        return app(AvailabilityService::class)->calendar(
            $month->startOfWeek(CarbonImmutable::MONDAY),
            $month->endOfMonth()->endOfWeek(CarbonImmutable::SUNDAY),
        );
    }

    public function previousMonth(): void
    {
        $this->calendarMonth = CarbonImmutable::createFromFormat('Y-m', $this->calendarMonth)
            ->subMonth()->format('Y-m');
        $this->persist();
    }

    public function nextMonth(): void
    {
        $this->calendarMonth = CarbonImmutable::createFromFormat('Y-m', $this->calendarMonth)
            ->addMonth()->format('Y-m');
        $this->persist();
    }

    public function selectDay(string $date): void
    {
        $this->dates[$this->activeDate]['event_date'] = $date;

        // Changing the day invalidates any slot already picked for it.
        $this->dates[$this->activeDate]['session_slot'] = null;
        $this->clashError = null;
        $this->persist();
    }

    public function selectSlot(string $slot): void
    {
        $this->dates[$this->activeDate]['session_slot'] = $slot;
        $this->clashError = null;
        $this->persist();
    }

    public function addDate(): void
    {
        // A nikah and a reception are one booking, under one reference.
        if (count($this->dates) < 4) {
            $this->dates[] = $this->emptyDate();
            $this->activeDate = count($this->dates) - 1;
            $this->persist();
        }
    }

    public function removeDate(int $index): void
    {
        if (count($this->dates) <= 1) {
            return;
        }

        unset($this->dates[$index]);
        $this->dates = array_values($this->dates);
        $this->activeDate = 0;
        $this->persist();
    }

    public function focusDate(int $index): void
    {
        $this->activeDate = $index;

        // Jump the calendar to the month that date is in, so the couple isn't
        // left staring at an unrelated month.
        if ($chosen = $this->dates[$index]['event_date'] ?? null) {
            $this->calendarMonth = CarbonImmutable::parse($chosen)->format('Y-m');
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Step 2 & 3 — package and add-ons
    |--------------------------------------------------------------------------
    */

    public function selectPackage(int $packageId): void
    {
        $this->packageId = $packageId;

        // Drop add-ons that aren't offered with the newly chosen package,
        // otherwise the running total silently includes something the couple
        // can no longer have.
        $package = Package::find($packageId);

        if ($package) {
            $this->addOns = collect($this->addOns)
                ->filter(fn (int $qty, int $id) => AddOn::find($id)?->appliesTo($package) ?? false)
                ->all();
        }

        $this->persist();
    }

    public function toggleAddOn(int $addOnId): void
    {
        if (isset($this->addOns[$addOnId])) {
            unset($this->addOns[$addOnId]);
        } else {
            $this->addOns[$addOnId] = 1;
        }

        $this->persist();
    }

    public function setAddOnQuantity(int $addOnId, int $qty): void
    {
        $addOn = AddOn::find($addOnId);

        if (! $addOn) {
            return;
        }

        if ($qty < 1) {
            unset($this->addOns[$addOnId]);
        } else {
            $this->addOns[$addOnId] = $addOn->clampQuantity($qty);
        }

        $this->persist();
    }

    /*
    |--------------------------------------------------------------------------
    | Derived data
    |--------------------------------------------------------------------------
    */

    public function getPackageProperty(): ?Package
    {
        return $this->packageId ? Package::active()->find($this->packageId) : null;
    }

    /** The live running total. Recomputed on every change, never cached. */
    public function getQuoteProperty(): Quote
    {
        return app(CalculateQuote::class)->handle($this->package, $this->addOns);
    }

    public function getAvailableAddOnsProperty()
    {
        return $this->package
            ? $this->package->availableAddOns()
            : collect();
    }

    /** Dates that are fully chosen — both a day and a slot. */
    public function completedDates(): array
    {
        return array_values(array_filter(
            $this->dates,
            fn (array $d) => filled($d['event_date']) && filled($d['session_slot']),
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    */

    public function nextStep(): void
    {
        $this->validateStep($this->step);

        // Re-check availability whenever the couple leaves the date step. The
        // calendar they looked at may be minutes old.
        if ($this->step === self::STEP_DATES && ! $this->datesStillAvailable()) {
            return;
        }

        $this->step = min($this->step + 1, self::STEP_REVIEW);
        $this->persist();
    }

    public function previousStep(): void
    {
        // Back navigation must never lose input — nothing is cleared here.
        $this->step = max($this->step - 1, self::STEP_DATES);
        $this->persist();
    }

    public function goToStep(int $step): void
    {
        // Only allow jumping backwards, so a couple can't skip the date step.
        if ($step < $this->step) {
            $this->step = $step;
            $this->persist();
        }
    }

    private function validateStep(int $step): void
    {
        $rules = match ($step) {
            self::STEP_DATES => [
                'dates' => ['required', 'array', 'min:1'],
                'dates.*.event_date' => ['required', 'date'],
                'dates.*.session_slot' => ['required'],
            ],
            self::STEP_PACKAGE => BookingRules::package(),
            self::STEP_ADDONS => BookingRules::addOns(),
            self::STEP_DETAILS => BookingRules::details(),
            self::STEP_REVIEW => BookingRules::review(),
            default => [],
        };

        $this->validate($rules, BookingRules::messages(), BookingRules::attributes());
    }

    /** Live clash check. Sets $clashError and returns false if anything is taken. */
    private function datesStillAvailable(): bool
    {
        $problems = app(AvailabilityService::class)->problemsWith(
            collect($this->completedDates())->map(fn (array $d) => [
                'date' => $d['event_date'],
                'slot' => SessionSlot::from($d['session_slot']),
            ])
        );

        $this->clashError = $problems === [] ? null : implode(' ', $problems);

        return $problems === [];
    }

    /*
    |--------------------------------------------------------------------------
    | Submit
    |--------------------------------------------------------------------------
    */

    public function submit(CreateBooking $createBooking): void
    {
        // Honeypot: a real person never sees this field.
        if (filled($this->website_url)) {
            return;
        }

        $this->validate(
            [...BookingRules::details(), ...BookingRules::package(), ...BookingRules::review()],
            BookingRules::messages(),
            BookingRules::attributes(),
        );

        // Rate limit per IP. No CAPTCHA, as the brief requires.
        $key = 'booking:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, (int) config('booking.rate_limit_per_hour'))) {
            $this->clashError = __('booking.errors.too_many', [
                'minutes' => ceil(RateLimiter::availableIn($key) / 60),
            ]);

            return;
        }

        try {
            $booking = $createBooking->handle([
                'package_id' => $this->packageId,
                'dates' => $this->completedDates(),
                'add_ons' => $this->addOns,
                'partner_one_name' => $this->partnerOneName,
                'partner_two_name' => $this->partnerTwoName ?: null,
                'email' => $this->email,
                'phone' => $this->phone,
                'guest_count' => $this->guestCount,
                'source' => $this->source,
                'notes' => $this->notes ?: null,
            ]);
        } catch (SlotUnavailableException $e) {
            /*
             * The stale-tab case. Someone confirmed this slot while the couple
             * was filling the form in. Say so plainly and send them back to
             * the calendar — never accept it silently.
             */
            $this->clashError = $e->getMessage();
            $this->step = self::STEP_DATES;
            $this->persist();

            return;
        }

        RateLimiter::hit($key, 3600);

        // Queued, so the couple gets their thank-you page immediately rather
        // than waiting on an SMTP handshake.
        app(SendBookingNotifications::class)->handle($booking);

        $this->forget();

        $this->redirectRoute('book.thanks', ['reference' => $booking->reference], navigate: true);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /** @return array{event_date: null, session_slot: null, label: null, venue: null, city: null, state: null} */
    private function emptyDate(): array
    {
        return [
            'event_date' => null,
            'session_slot' => null,
            'label' => null,
            'venue' => null,
            'city' => null,
            'state' => null,
        ];
    }

    public function render()
    {
        return view('livewire.booking-wizard', [
            'packages' => Package::active()->ordered()->get(),
            'sources' => EnquirySource::cases(),
            'slots' => SessionSlot::cases(),
        ]);
    }
}
