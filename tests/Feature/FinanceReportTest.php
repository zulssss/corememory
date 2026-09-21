<?php

declare(strict_types=1);

use App\Enums\BookingStatus;
use App\Enums\InvoiceStatus;
use App\Models\Booking;
use App\Models\BookingCost;
use App\Models\BookingDate;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\FinanceReport;
use App\ValueObjects\Money;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
    $this->from = now()->startOfMonth();
    $this->to = now()->endOfMonth();
});

/** Named financeReport, not report — financeReport() is a Laravel global helper. */
function financeReport(string $basis = FinanceReport::BASIS_CASH): FinanceReport
{
    return FinanceReport::for(test()->from, test()->to, $basis);
}

describe('invoiced is not collected', function () {
    it('counts only money received on a cash basis', function () {
        $invoice = Invoice::factory()->sent()->totalling(500000)->create(['issued_at' => today()]);
        Payment::factory()->of(200000)->create(['invoice_id' => $invoice->id, 'paid_at' => today()]);

        expect(financeReport(FinanceReport::BASIS_CASH)->revenue())->toBeMoney(200000);
    });

    it('counts the whole invoice on an accrual basis', function () {
        $invoice = Invoice::factory()->sent()->totalling(500000)->create(['issued_at' => today()]);
        Payment::factory()->of(200000)->create(['invoice_id' => $invoice->id, 'paid_at' => today()]);

        expect(financeReport(FinanceReport::BASIS_ACCRUAL)->revenue())->toBeMoney(500000);
    });

    it('never counts a draft invoice as revenue', function () {
        Invoice::factory()->totalling(500000)->create([
            'status' => InvoiceStatus::Draft,
            'issued_at' => today(),
        ]);

        expect(financeReport(FinanceReport::BASIS_ACCRUAL)->invoiced())->toBeMoney(0);
    });

    it('excludes payments outside the period', function () {
        $invoice = Invoice::factory()->sent()->create();
        Payment::factory()->of(100000)->create(['invoice_id' => $invoice->id, 'paid_at' => today()]);
        Payment::factory()->of(999999)->create([
            'invoice_id' => $invoice->id,
            'paid_at' => today()->subMonths(3),
        ]);

        expect(financeReport()->collected())->toBeMoney(100000);
    });
});

describe('profit', function () {
    it('subtracts direct costs from revenue', function () {
        $booking = Booking::factory()->create();
        BookingDate::factory()->for($booking)->create(['event_date' => today()]);
        BookingCost::factory()->of(120000)->create(['booking_id' => $booking->id]);

        $invoice = Invoice::factory()->sent()->totalling(500000)->create(['issued_at' => today()]);
        Payment::factory()->of(500000)->create(['invoice_id' => $invoice->id, 'paid_at' => today()]);

        $report = financeReport();

        expect($report->costs())->toBeMoney(120000)
            ->and($report->grossProfit())->toBeMoney(380000)
            ->and($report->grossMarginPercent())->toBe(76.0);
    });

    it('reports no margin rather than 0% when there is no revenue', function () {
        // 0% would read as a real result. It is not.
        expect(financeReport()->grossMarginPercent())->toBeNull();
    });

    it('dates costs by the event, not when they were entered', function () {
        // A cost keyed in late must still land in the month the work happened.
        $booking = Booking::factory()->create();
        BookingDate::factory()->for($booking)->create(['event_date' => today()->subMonths(4)]);
        BookingCost::factory()->of(120000)->create(['booking_id' => $booking->id]);

        expect(financeReport()->costs())->toBeMoney(0);
    });

    it('computes per-booking profit against collected revenue', function () {
        $booking = Booking::factory()->create();
        BookingCost::factory()->of(200000)->create(['booking_id' => $booking->id]);

        $invoice = Invoice::factory()->sent()->totalling(600000)
            ->create(['booking_id' => $booking->id]);
        Payment::factory()->of(600000)->create(['invoice_id' => $invoice->id]);

        $booking = $booking->fresh(['costs', 'invoices.payments']);

        expect($booking->totalCosts())->toBeMoney(200000)
            ->and($booking->collectedRevenue())->toBeMoney(600000)
            ->and($booking->grossProfit())->toBeMoney(400000)
            ->and($booking->grossMarginPercent())->toBe(66.7);
    });
});

describe('receivables', function () {
    it('counts what is still owed, not the invoice total', function () {
        $invoice = Invoice::factory()->sent()->totalling(500000)->create();
        Payment::factory()->of(200000)->create(['invoice_id' => $invoice->id]);

        expect(financeReport()->receivables()['total'])->toBeMoney(300000);
    });

    it('ignores settled and draft invoices', function () {
        $paid = Invoice::factory()->paid()->totalling(400000)->create();
        Payment::factory()->of(400000)->create(['invoice_id' => $paid->id]);
        Invoice::factory()->totalling(900000)->create(['status' => InvoiceStatus::Draft]);

        expect(financeReport()->receivables()['total'])->toBeMoney(0);
    });

    it('splits outstanding money into ageing buckets', function () {
        Invoice::factory()->overdue(10)->totalling(100000)->create();
        Invoice::factory()->overdue(45)->totalling(200000)->create();
        Invoice::factory()->overdue(90)->totalling(300000)->create();
        Invoice::factory()->sent()->totalling(400000)->create(['due_at' => today()->addWeek()]);

        $buckets = financeReport()->receivables()['buckets'];

        expect($buckets['0-30'])->toBeMoney(100000)
            ->and($buckets['31-60'])->toBeMoney(200000)
            ->and($buckets['60+'])->toBeMoney(300000)
            ->and($buckets['not_due'])->toBeMoney(400000);
    });

    it('reports receivables regardless of the date filter', function () {
        // Money owed from four months ago is still owed today. Hiding it
        // behind a date range is how it gets forgotten.
        Invoice::factory()->overdue(120)->totalling(250000)->create();

        expect(financeReport()->receivables()['total'])->toBeMoney(250000);
    });
});

describe('the funnel', function () {
    it('counts each stage cumulatively', function () {
        Booking::factory()->count(4)->create(['status' => BookingStatus::Pending]);
        Booking::factory()->count(3)->create(['status' => BookingStatus::Quoted]);
        Booking::factory()->count(2)->create(['status' => BookingStatus::Confirmed]);
        Booking::factory()->create(['status' => BookingStatus::Completed]);

        // A completed booking was also once confirmed and once quoted —
        // counting it only in the last bucket would understate every stage.
        expect(financeReport()->funnel())->toBe([
            'enquiries' => 10,
            'quoted' => 6,
            'confirmed' => 3,
            'completed' => 1,
        ]);
    });

    it('reports conversion from enquiry to confirmed', function () {
        Booking::factory()->count(8)->create(['status' => BookingStatus::Pending]);
        Booking::factory()->count(2)->create(['status' => BookingStatus::Confirmed]);

        expect(financeReport()->conversionRate())->toBe(20.0);
    });

    it('has no conversion rate without enquiries', function () {
        expect(financeReport()->conversionRate())->toBeNull();
    });
});

describe('caching', function () {
    it('survives a second read', function () {
        // Regression: caching Money objects returns __PHP_Incomplete_Class on
        // the second read, so the dashboard dies five minutes after it worked.
        Invoice::factory()->sent()->totalling(500000)->create(['issued_at' => today()]);

        $first = financeReport()->receivables();
        $second = financeReport()->receivables();

        expect($second['total']->cents)->toBe($first['total']->cents)
            ->and($second['buckets']['0-30'])->toBeInstanceOf(Money::class);
    });

    it('keeps separate figures per basis and period', function () {
        $invoice = Invoice::factory()->sent()->totalling(500000)->create(['issued_at' => today()]);
        Payment::factory()->of(100000)->create(['invoice_id' => $invoice->id, 'paid_at' => today()]);

        expect(financeReport(FinanceReport::BASIS_CASH)->revenue())->toBeMoney(100000)
            ->and(financeReport(FinanceReport::BASIS_ACCRUAL)->revenue())->toBeMoney(500000);
    });
});

describe('the monthly series', function () {
    it('returns twelve months of revenue, cost and profit', function () {
        $series = financeReport()->monthlySeries(12);

        expect($series['labels'])->toHaveCount(12)
            ->and($series['revenue'])->toHaveCount(12)
            ->and($series['costs'])->toHaveCount(12)
            ->and($series['profit'])->toHaveCount(12);
    });

    it('computes profit as revenue minus cost per month', function () {
        $invoice = Invoice::factory()->sent()->totalling(500000)->create(['issued_at' => today()]);
        Payment::factory()->of(500000)->create(['invoice_id' => $invoice->id, 'paid_at' => today()]);

        $booking = Booking::factory()->create();
        BookingDate::factory()->for($booking)->create(['event_date' => today()]);
        BookingCost::factory()->of(150000)->create(['booking_id' => $booking->id]);

        $series = financeReport()->monthlySeries(12);

        expect(end($series['revenue']))->toBe(5000.0)
            ->and(end($series['costs']))->toBe(1500.0)
            ->and(end($series['profit']))->toBe(3500.0);
    });
});
