<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Invoices\GenerateInvoice;
use App\Actions\Invoices\IssueInvoice;
use App\Actions\Invoices\RecordPayment;
use App\Enums\BookingStatus;
use App\Enums\CostCategory;
use App\Enums\InvoiceType;
use App\Enums\PaymentMethod;
use App\Models\Booking;
use App\Models\BookingCost;
use Illuminate\Database\Seeder;

/**
 * Invoices, payments and direct costs for the seeded bookings.
 *
 * The point is a dashboard with realistic SHAPES rather than a demo where
 * everything is paid and every margin is identical. So this deliberately
 * produces a mix: paid, part-paid, overdue, and bookings still without an
 * invoice at all.
 *
 * Everything goes through the real Actions, so numbering comes from the
 * sequence and the deposit/final pair reconciles — seeded data that bypassed
 * them would be a poor rehearsal for production.
 */
class FinanceSeeder extends Seeder
{
    public function run(): void
    {
        $generate = app(GenerateInvoice::class);
        $issue = app(IssueInvoice::class);
        $record = app(RecordPayment::class);

        $bookings = Booking::query()
            ->whereIn('status', [
                BookingStatus::Confirmed->value,
                BookingStatus::DepositPaid->value,
                BookingStatus::Quoted->value,
            ])
            ->with(['package', 'addOns', 'dates'])
            ->get();

        // Completed weddings are fully invoiced, fully paid and carry their
        // delivery costs — that is what gives the 12-month profit chart a
        // real series rather than a flat line.
        $this->seedCompletedHistory($generate, $issue, $record);

        foreach ($bookings as $index => $booking) {
            $this->seedCosts($booking);

            // A quoted booking has no invoice yet — that is a real state, and
            // the funnel should show it.
            if ($booking->status === BookingStatus::Quoted) {
                continue;
            }

            $deposit = $generate->handle($booking, InvoiceType::Deposit);
            $issue->handle($deposit);

            // Rotate through outcomes so every dashboard widget has something
            // to report: paid, part-paid, and overdue-unpaid.
            match ($index % 3) {
                0 => $record->handle($deposit, [
                    'amount_cents' => $deposit->total_cents->cents,
                    'paid_at' => today()->subDays(random_int(3, 40)),
                    'method' => PaymentMethod::BankTransfer,
                    'reference' => 'TRX'.random_int(100000, 999999),
                ]),

                1 => $record->handle($deposit, [
                    // A part payment: the invoice stays Sent and still shows
                    // in receivables for the remainder.
                    'amount_cents' => (int) round($deposit->total_cents->cents / 2),
                    'paid_at' => today()->subDays(random_int(3, 20)),
                    'method' => PaymentMethod::EWallet,
                    'reference' => 'EW'.random_int(100000, 999999),
                ]),

                // Left unpaid, and backdated so it reads as overdue.
                default => $deposit->forceFill([
                    'issued_at' => today()->subDays(random_int(40, 90)),
                    'due_at' => today()->subDays(random_int(5, 70)),
                ])->save(),
            };

            // Bookings whose event has passed also get a final invoice.
            if ($booking->status === BookingStatus::DepositPaid) {
                $final = $generate->handle($booking, InvoiceType::Final);
                $issue->handle($final);

                if ($index % 2 === 0) {
                    $record->handle($final, [
                        'amount_cents' => $final->total_cents->cents,
                        'paid_at' => today()->subDays(random_int(1, 15)),
                        'method' => PaymentMethod::BankTransfer,
                        'reference' => 'TRX'.random_int(100000, 999999),
                    ]);
                }
            }
        }
    }

    /**
     * A completed wedding: deposit and final invoice, both settled, plus the
     * direct costs of delivering it.
     *
     * Invoices are dated around the EVENT, not today, so revenue lands in the
     * month the work happened.
     */
    private function seedCompletedHistory($generate, $issue, $record): void
    {
        $completed = Booking::query()
            ->where('status', BookingStatus::Completed)
            ->with(['package', 'addOns', 'dates'])
            ->get();

        foreach ($completed as $booking) {
            $eventDate = $booking->dates->min('event_date');

            if ($eventDate === null) {
                continue;
            }

            $this->seedCosts($booking);

            foreach ([InvoiceType::Deposit, InvoiceType::Final] as $type) {
                $invoice = $generate->handle($booking, $type);
                $issue->handle($invoice);

                $issuedAt = $type === InvoiceType::Deposit
                    ? $eventDate->copy()->subMonths(2)
                    : $eventDate->copy()->addDays(3);

                $invoice->forceFill([
                    'issued_at' => $issuedAt,
                    'due_at' => $issuedAt->copy()->addDays(14),
                ])->save();

                $record->handle($invoice, [
                    'amount_cents' => $invoice->total_cents->cents,
                    'paid_at' => $issuedAt->copy()->addDays(random_int(1, 12)),
                    'method' => PaymentMethod::BankTransfer,
                    'reference' => 'TRX'.random_int(100000, 999999),
                ]);
            }
        }
    }

    /**
     * Direct costs, scaled to the booking so margins vary realistically
     * instead of every job showing the same percentage.
     */
    private function seedCosts(Booking $booking): void
    {
        $total = $booking->estimated_total_cents->cents;

        if ($total === 0) {
            return;
        }

        // Roughly 45–65% of revenue goes out as direct cost.
        $costRatio = random_int(45, 65) / 100;
        $budget = (int) round($total * $costRatio);

        $split = [
            [CostCategory::Photographer, 0.38],
            [CostCategory::Editor, 0.22],
            [CostCategory::Travel, 0.14],
            [CostCategory::Equipment, 0.12],
            [CostCategory::Printing, 0.14],
        ];

        foreach ($split as [$category, $share]) {
            BookingCost::create([
                'booking_id' => $booking->getKey(),
                'label' => $category->label(),
                'category' => $category,
                'amount_cents' => (int) round($budget * $share),
                'is_paid' => random_int(1, 10) > 3,
            ]);
        }
    }
}
