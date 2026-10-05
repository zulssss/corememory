<?php

declare(strict_types=1);

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Enums\SessionSlot;
use App\Exceptions\SlotUnavailableException;
use App\Models\Booking;
use App\Models\BookingDate;
use App\Models\Package;
use App\Models\Sequence;
use App\Services\AvailabilityService;
use App\Support\Phone;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Creates a booking ENQUIRY from the wizard.
 *
 * What this does NOT do: hold the date. A new booking is `pending`, which is
 * tentative by default (config/booking.php), so more than one couple may
 * enquire for the same slot and the studio decides between them. Holds are
 * written by ConfirmBooking.
 *
 * What it DOES guarantee: you cannot enquire onto a slot that is already
 * *blocked*. That check happens inside the transaction, so a stale tab whose
 * slot was confirmed while the couple was typing is rejected plainly rather
 * than accepted and quietly double-booked.
 */
class CreateBooking
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly CalculateQuote $calculateQuote,
    ) {}

    /**
     * @param  array{
     *     package_id: int,
     *     dates: list<array{event_date: string, session_slot: string, label?: string|null, venue?: string|null, city?: string|null, state?: string|null}>,
     *     add_ons?: array<int, int>,
     *     partner_one_name: string,
     *     partner_two_name?: string|null,
     *     email: string,
     *     phone: string,
     *     guest_count?: int|null,
     *     source?: string|null,
     *     notes?: string|null,
     * }  $data
     *
     * @throws SlotUnavailableException
     */
    public function handle(array $data): Booking
    {
        $package = Package::active()->findOrFail($data['package_id']);
        $quote = $this->calculateQuote->handle(
            $package,
            $data['add_ons'] ?? [],
            max(1, count($data['dates'] ?? [])),
        );

        return DB::transaction(function () use ($data, $package, $quote): Booking {
            /*
             * Re-check inside the transaction. The wizard already checked when
             * the couple picked a date, but that was minutes ago — this catches
             * the slot being confirmed by someone else in the meantime.
             *
             * Pending does not hold, so this is a read, not a lock. The
             * absolute guarantee lives in ConfirmBooking, where the unique
             * index does the work.
             */
            foreach ($data['dates'] as $date) {
                $day = CarbonImmutable::parse($date['event_date'])->startOfDay();
                $slot = SessionSlot::from($date['session_slot']);

                $state = $this->availability->check($day, $slot);

                if (! $state->bookable) {
                    throw new SlotUnavailableException(
                        $day,
                        $slot,
                        $state->reason ?? __('booking.errors.slot_taken'),
                    );
                }
            }

            $booking = Booking::create([
                'reference' => $this->allocateReference(),
                'package_id' => $package->getKey(),
                'partner_one_name' => $data['partner_one_name'],
                'partner_two_name' => self::optional($data, 'partner_two_name'),
                'email' => $data['email'],
                'phone' => Phone::format($data['phone']),
                'guest_count' => self::optional($data, 'guest_count'),
                'source' => self::optional($data, 'source'),
                'notes' => self::optional($data, 'notes'),

                // Captured at booking time. A later price change must never
                // rewrite this enquiry or any invoice raised from it.
                'subtotal_cents' => $quote->subtotal,
                'addons_total_cents' => $quote->addOnsTotal,
                'estimated_total_cents' => $quote->total,
                'deposit_cents' => $quote->deposit,

                'status' => BookingStatus::Pending,
            ]);

            foreach ($data['dates'] as $date) {
                BookingDate::create([
                    'booking_id' => $booking->getKey(),
                    'event_date' => CarbonImmutable::parse($date['event_date'])->startOfDay(),
                    'session_slot' => $date['session_slot'],
                    'label' => $date['label'] ?? null,
                    'venue' => $date['venue'] ?? null,
                    'city' => $date['city'] ?? null,
                    'state' => $date['state'] ?? null,
                ]);
            }

            foreach ($quote->lines as $line) {
                $booking->addOns()->attach($line->addOn->getKey(), [
                    'qty' => $line->qty,
                    'price_cents_at_booking' => $line->unitPrice->cents,
                    'line_total_cents' => $line->lineTotal->cents,
                ]);
            }

            /*
             * If pending is ever made a blocking status in config, holds must
             * be written here too. Doing it by asking the enum — rather than
             * hardcoding — is what makes that a config change, not a code
             * change, exactly as the brief requires.
             */
            if (BookingStatus::Pending->blocksSlot()) {
                foreach ($booking->dates as $bookingDate) {
                    $this->availability->hold(
                        $bookingDate,
                        $bookingDate->event_date,
                        $bookingDate->session_slot,
                    );
                }
            }

            $booking->notes()->create([
                'body' => __('booking.notes.created'),
                'is_system' => true,
            ]);

            return $booking->fresh(['dates', 'addOns', 'package']);
        });
    }

    /**
     * CM-2026-0001. Sequential per calendar year, never reused.
     *
     * Uses the locked sequences row rather than count() + 1, which would reuse
     * a number after any delete and hand two simultaneous requests the same
     * value.
     */
    private function allocateReference(): string
    {
        $year = (int) now()->year;
        $number = Sequence::next('booking', $year);

        return sprintf(
            '%s-%d-%s',
            config('booking.reference_prefix'),
            $year,
            str_pad((string) $number, (int) config('booking.sequence_padding'), '0', STR_PAD_LEFT),
        );
    }

    /**
     * An optional field, with blank collapsed to null.
     *
     * `?? null` is not enough: it replaces null but passes '' straight through.
     * A form control left on its empty option submits '', and '' is not a
     * valid EnquirySource — the cast threw a 500 on "Send booking request" for
     * any couple who had touched "How did you find us?". Normalised here, not
     * in the wizard, so every caller of this action is covered.
     */
    private static function optional(array $data, string $key): mixed
    {
        $value = $data[$key] ?? null;

        return blank($value) ? null : $value;
    }
}
