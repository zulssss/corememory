<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Bookings\CalculateQuote;
use App\Actions\Bookings\ConfirmBooking;
use App\Actions\Bookings\CreateBooking;
use App\Enums\BookingStatus;
use App\Enums\EnquirySource;
use App\Enums\SessionSlot;
use App\Models\AddOn;
use App\Models\BlockedDate;
use App\Models\Booking;
use App\Models\Package;
use App\Models\Sequence;
use App\Models\SlotHold;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Demo bookings spread across every status, so the admin panel and the Phase 4
 * profit dashboard have realistic shapes to display rather than an empty table.
 *
 * Bookings go through the real CreateBooking action — the same code path the
 * public wizard uses — so references come from the sequence and the totals are
 * calculated by CalculateQuote. Seeded data that bypassed the Actions would be
 * a poor rehearsal for production.
 *
 * All couples are invented. No real client appears here.
 */
class BookingSeeder extends Seeder
{
    private const COUPLES = [
        ['Aisyah', 'Danial', 'Selangor', 'Shah Alam'],
        ['Farah', 'Hafiz', 'Wilayah Persekutuan', 'Kuala Lumpur'],
        ['Mei Ling', 'Wei Sheng', 'Selangor', 'Petaling Jaya'],
        ['Nurul', 'Iskandar', 'Johor', 'Johor Bahru'],
        ['Priya', 'Arun', 'Wilayah Persekutuan', 'Kuala Lumpur'],
        ['Siti', 'Rahman', 'Pahang', 'Kuantan'],
        ['Hani', 'Zulkifli', 'Penang', 'George Town'],
        ['Chloe', 'Marcus', 'Selangor', 'Subang Jaya'],
        ['Izzati', 'Faiz', 'Melaka', 'Melaka'],
        ['Divya', 'Karthik', 'Selangor', 'Klang'],
        ['Amira', 'Syafiq', 'Negeri Sembilan', 'Seremban'],
        ['Wan', 'Adib', 'Terengganu', 'Kuala Terengganu'],
    ];

    public function run(): void
    {
        $packages = Package::active()->ordered()->get();
        $addOns = AddOn::active()->get();
        $create = app(CreateBooking::class);
        $confirm = app(ConfirmBooking::class);

        if ($packages->isEmpty()) {
            return;
        }

        // Walk forward in distinct weeks so seeded confirmations never collide.
        // A clash here would be correct behaviour, but it would make seeding
        // fail unpredictably — the conflict rules are proven in the test suite.
        $cursor = CarbonImmutable::today()->addDays((int) config('booking.min_lead_days') + 7);

        foreach (self::COUPLES as $index => [$one, $two, $state, $city]) {
            $package = $packages[$index % $packages->count()];
            $eventDate = $cursor->addWeeks($index * 2);

            // Roughly a third of bookings are two-day: a nikah in the morning
            // and a reception the following evening. This is the common
            // Malaysian shape and the reason booking_dates exists.
            $isTwoDay = $index % 3 === 0;

            $dates = $isTwoDay
                ? [
                    [
                        'event_date' => $eventDate->toDateString(),
                        'session_slot' => SessionSlot::Morning->value,
                        'label' => 'Nikah',
                        'venue' => 'Masjid '.$city,
                        'city' => $city,
                        'state' => $state,
                    ],
                    [
                        'event_date' => $eventDate->addDay()->toDateString(),
                        'session_slot' => SessionSlot::Evening->value,
                        'label' => 'Reception',
                        'venue' => 'Dewan '.$city,
                        'city' => $city,
                        'state' => $state,
                    ],
                ]
                : [[
                    'event_date' => $eventDate->toDateString(),
                    'session_slot' => SessionSlot::FullDay->value,
                    'label' => 'Wedding',
                    'venue' => 'Dewan '.$city,
                    'city' => $city,
                    'state' => $state,
                ]];

            // A couple of add-ons on most bookings, so the attach-rate metric
            // in Phase 4 has something to report.
            $chosen = [];
            foreach ($addOns->random(min(2, $addOns->count())) as $addOn) {
                $chosen[$addOn->getKey()] = $addOn->is_quantifiable ? random_int(1, 3) : 1;
            }

            $booking = $create->handle([
                'package_id' => $package->getKey(),
                'dates' => $dates,
                'add_ons' => $index % 4 === 0 ? [] : $chosen,
                'partner_one_name' => $one,
                'partner_two_name' => $two,
                'email' => strtolower($one).'@example.test',
                'phone' => '01'.random_int(1, 9).'-'.random_int(200, 999).' '.random_int(1000, 9999),
                'guest_count' => random_int(80, 600) - (random_int(80, 600) % 20),
                'source' => $this->source($index)->value,
                'notes' => $index % 5 === 0
                    ? 'We would like to include a short family portrait session after the ceremony.'
                    : null,
            ]);

            $this->applyStatus($booking, $index, $confirm);
        }

        $this->seedHistory($packages, $addOns);
        $this->seedBlockedDates();
    }

    /**
     * Completed weddings across the last 12 months.
     *
     * The dashboard's headline chart is revenue vs cost vs profit over 12
     * months, and its seasonality view is bookings by month. Neither shows
     * anything if every seeded wedding is in the future — the demo data needs
     * a past as well as a diary.
     *
     * These are built DIRECTLY rather than through CreateBooking, and that is
     * deliberate. CreateBooking refuses a date in the past, which is exactly
     * right for a booking funnel — you cannot book last March. Back-filling a
     * completed job is a different operation, so it does not pretend to be one.
     * The reference still comes from the sequence and the totals still come
     * from CalculateQuote, so the records are indistinguishable otherwise.
     *
     * Completed does not hold a slot, so these never collide with the upcoming
     * bookings above.
     */
    private function seedHistory($packages, $addOns): void
    {
        if ($packages->isEmpty()) {
            return;
        }

        $calculateQuote = app(CalculateQuote::class);

        $couples = [
            ['Lina', 'Haziq'], ['Yasmin', 'Rizal'], ['Ain', 'Shahrul'],
            ['Kavitha', 'Suresh'], ['Belinda', 'Jun Hao'], ['Nadia', 'Firdaus'],
            ['Suhana', 'Azlan'], ['Rachel', 'Daniel'], ['Zara', 'Imran'],
            ['Alia', 'Haikal'], ['Serena', 'Kai'], ['Maya', 'Arif'],
            ['Dina', 'Rashid'], ['Elena', 'Wei Jie'], ['Nur', 'Syazwan'],
            ['Tania', 'Vikram'], ['Sofia', 'Zaki'], ['Hana', 'Luqman'],
        ];

        foreach ($couples as $index => [$one, $two]) {
            $eventDate = CarbonImmutable::today()
                ->subMonths(12)
                ->addDays((int) round($index * (365 / count($couples))));

            $package = $packages[$index % $packages->count()];

            $chosen = [];
            foreach ($addOns->random(min(2, $addOns->count())) as $addOn) {
                $chosen[$addOn->getKey()] = $addOn->is_quantifiable ? random_int(1, 3) : 1;
            }

            $quote = $calculateQuote->handle($package, $chosen);
            $year = (int) $eventDate->year;

            $booking = Booking::create([
                'reference' => sprintf(
                    '%s-%d-%s',
                    config('booking.reference_prefix'),
                    $year,
                    str_pad((string) Sequence::next('booking', $year), 4, '0', STR_PAD_LEFT),
                ),
                'package_id' => $package->getKey(),
                'partner_one_name' => $one,
                'partner_two_name' => $two,
                'email' => strtolower($one).'@example.test',
                'phone' => '01'.random_int(1, 9).'-'.random_int(200, 999).' '.random_int(1000, 9999),
                'guest_count' => random_int(80, 600),
                'source' => $this->source($index),
                'subtotal_cents' => $quote->subtotal,
                'addons_total_cents' => $quote->addOnsTotal,
                'estimated_total_cents' => $quote->total,
                'deposit_cents' => $quote->deposit,
                'status' => BookingStatus::Completed,
            ]);

            // Enquired a few months before the wedding, so the funnel and the
            // seasonality view both read correctly.
            $booking->forceFill([
                'created_at' => $eventDate->subMonths(random_int(3, 9)),
            ])->save();

            $booking->dates()->create([
                'event_date' => $eventDate,
                'session_slot' => SessionSlot::FullDay,
                'label' => 'Wedding',
                'venue' => 'Dewan Placeholder',
                'city' => 'Kuala Lumpur',
                'state' => 'Wilayah Persekutuan',
            ]);

            foreach ($quote->lines as $line) {
                $booking->addOns()->attach($line->addOn->getKey(), [
                    'qty' => $line->qty,
                    'price_cents_at_booking' => $line->unitPrice->cents,
                    'line_total_cents' => $line->lineTotal->cents,
                ]);
            }
        }
    }

    /**
     * Spread bookings across the funnel so the dashboard shows a realistic
     * enquiries -> quoted -> confirmed -> completed shape rather than a flat one.
     */
    private function applyStatus(Booking $booking, int $index, ConfirmBooking $confirm): void
    {
        match ($index % 6) {
            0, 1 => $confirm->handle($booking, BookingStatus::Confirmed),
            2 => $confirm->handle($booking, BookingStatus::DepositPaid),
            3 => $booking->forceFill(['status' => BookingStatus::Quoted])->save(),
            4 => $booking->forceFill(['status' => BookingStatus::Contacted])->save(),
            default => null,   // stays pending
        };
    }

    private function source(int $index): EnquirySource
    {
        // Instagram dominates, which is the whole reason this site exists.
        return match ($index % 6) {
            0, 1, 2 => EnquirySource::Instagram,
            3 => EnquirySource::Referral,
            4 => EnquirySource::Google,
            default => EnquirySource::TikTok,
        };
    }

    /** A few hand-blocked dates, so the calendar shows manual blocks too. */
    private function seedBlockedDates(): void
    {
        $base = CarbonImmutable::today()->addMonths(3);

        foreach ([
            ['date' => $base, 'slot' => null, 'reason' => 'Studio leave'],
            ['date' => $base->addDay(), 'slot' => null, 'reason' => 'Studio leave'],
            ['date' => $base->addMonths(2), 'slot' => SessionSlot::Morning, 'reason' => 'Equipment servicing'],
        ] as $block) {
            // Skip anything a seeded booking already holds — the observer would
            // (correctly) refuse, and seeding should not depend on ordering.
            $exists = SlotHold::whereDate('event_date', $block['date'])->exists();

            if ($exists) {
                continue;
            }

            BlockedDate::create([
                'date' => $block['date'],
                'session_slot' => $block['slot'],
                'reason' => $block['reason'],
            ]);
        }
    }
}
