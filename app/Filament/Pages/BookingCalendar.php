<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\SessionSlot;
use App\Models\BlockedDate;
use App\Models\BookingDate;
use App\Models\SlotHold;
use BackedEnum;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use UnitEnum;

/**
 * A month view of what the studio actually has on: confirmed bookings,
 * tentative enquiries, and manually blocked dates, by date and slot.
 *
 * The bookings list answers "what enquiries do we have?". This answers
 * "are we free on the 14th?", which is a different question and the one the
 * studio is asked on the phone.
 *
 * Everything is loaded in two queries for the whole month — never one per day.
 */
class BookingCalendar extends Page
{
    protected string $view = 'filament.pages.booking-calendar';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Calendar';

    protected static string|UnitEnum|null $navigationGroup = 'Bookings';

    protected static ?int $navigationSort = 0;

    protected static ?string $title = 'Calendar';

    public string $month = '';

    public function mount(): void
    {
        $this->month = CarbonImmutable::today()->format('Y-m');
    }

    public function previousMonth(): void
    {
        $this->month = $this->monthStart()->subMonth()->format('Y-m');
    }

    public function nextMonth(): void
    {
        $this->month = $this->monthStart()->addMonth()->format('Y-m');
    }

    public function today(): void
    {
        $this->month = CarbonImmutable::today()->format('Y-m');
    }

    public function monthStart(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m', $this->month)->startOfMonth();
    }

    /**
     * The grid, keyed by date.
     *
     * @return array<string, array{date: CarbonImmutable, in_month: bool, is_today: bool, slots: array<string, array{state: string, label: string|null, reference: string|null, id: int|null}>}>
     */
    public function getWeeksProperty(): array
    {
        $start = $this->monthStart()->startOfWeek(CarbonImmutable::MONDAY);
        $end = $this->monthStart()->endOfMonth()->endOfWeek(CarbonImmutable::SUNDAY);

        // Query 1 — everything that holds a slot, with what holds it.
        //
        // holdable is a morph to two different models, so a plain
        // with('holdable.booking') fails: BlockedDate has no booking relation.
        // morphWith says "only load booking when the holdable is a BookingDate".
        $holds = SlotHold::query()
            ->whereBetween('event_date', [$start->toDateString(), $end->toDateString()])
            ->with(['holdable' => fn (MorphTo $morphTo) => $morphTo->morphWith([
                BookingDate::class => ['booking'],
                BlockedDate::class => [],
            ])])
            ->get()
            ->groupBy(fn (SlotHold $h) => $h->event_date->toDateString());

        // Query 2 — tentative enquiries, which hold nothing but still matter.
        $tentative = BookingDate::query()
            ->whereBetween('event_date', [$start->toDateString(), $end->toDateString()])
            ->whereHas('booking', fn ($q) => $q->tentative())
            ->with('booking:id,reference,partner_one_name,partner_two_name,status')
            ->get()
            ->groupBy(fn (BookingDate $d) => $d->event_date->toDateString());

        $days = [];

        foreach (CarbonPeriod::create($start, $end) as $day) {
            $day = CarbonImmutable::parse($day);
            $key = $day->toDateString();

            $days[$key] = [
                'date' => $day,
                'in_month' => $day->format('Y-m') === $this->month,
                'is_today' => $day->isToday(),
                'slots' => $this->slotsFor($holds->get($key), $tentative->get($key)),
            ];
        }

        return $days;
    }

    /** @return array<string, array{state: string, label: string|null, reference: string|null, id: int|null}> */
    private function slotsFor($holds, $tentativeDates): array
    {
        $slots = [];

        foreach (SessionSlot::concrete() as $slot) {
            $hold = $holds?->firstWhere('session_slot', $slot);

            if ($hold) {
                $holdable = $hold->holdable;

                // A hold belongs to either a BookingDate or a BlockedDate.
                $booking = $holdable instanceof BookingDate ? $holdable->booking : null;

                $slots[$slot->value] = [
                    'state' => $booking ? 'booked' : 'blocked',
                    'label' => $booking?->coupleNames() ?? ($hold->reason ?: 'Blocked'),
                    'reference' => $booking?->reference,
                    'id' => $booking?->getKey(),
                ];

                continue;
            }

            // Tentative: a pending enquiry, expanded so a pending full day
            // marks all three slots.
            $pending = $tentativeDates?->first(
                fn (BookingDate $d) => in_array($slot, $d->session_slot->expand(), true)
            );

            $slots[$slot->value] = $pending
                ? [
                    'state' => 'tentative',
                    'label' => $pending->booking->coupleNames(),
                    'reference' => $pending->booking->reference,
                    'id' => $pending->booking->getKey(),
                ]
                : ['state' => 'free', 'label' => null, 'reference' => null, 'id' => null];
        }

        return $slots;
    }
}
