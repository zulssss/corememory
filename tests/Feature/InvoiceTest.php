<?php

declare(strict_types=1);

use App\Actions\Invoices\GenerateInvoice;
use App\Actions\Invoices\IssueInvoice;
use App\Actions\Invoices\RecordPayment;
use App\Actions\Invoices\RenderInvoicePdf;
use App\Actions\Invoices\SendInvoice;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PaymentMethod;
use App\Mail\InvoiceMail;
use App\Models\AddOn;
use App\Models\Booking;
use App\Models\BookingDate;
use App\Models\Invoice;
use App\Models\Package;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->package = Package::factory()->priced(680000)->create(['name' => 'Wedding Classic']);

    $this->booking = Booking::factory()->for($this->package)->create([
        'partner_one_name' => 'Aisyah',
        'partner_two_name' => 'Danial',
        'email' => 'aisyah@example.test',
        'subtotal_cents' => 680000,
        'addons_total_cents' => 135000,
        'estimated_total_cents' => 815000,
        'deposit_cents' => 244500,   // 30%
    ]);

    BookingDate::factory()->for($this->booking)->create();

    $addOn = AddOn::factory()->quantifiable(6)->priced(45000)->create(['name' => 'Extra hour']);
    $this->booking->addOns()->attach($addOn->id, [
        'qty' => 3, 'price_cents_at_booking' => 45000, 'line_total_cents' => 135000,
    ]);
});

describe('numbering', function () {
    it('allocates sequential numbers that are never reused', function () {
        $first = app(GenerateInvoice::class)->handle($this->booking, InvoiceType::Deposit);
        $second = app(GenerateInvoice::class)->handle($this->booking, InvoiceType::Final);

        expect($first->number)->toBe('CM-INV-'.now()->year.'-0001')
            ->and($second->number)->toBe('CM-INV-'.now()->year.'-0002');

        // Deleting must NOT free the number for reuse — count()+1 would.
        $second->delete();
        $third = app(GenerateInvoice::class)->handle($this->booking, InvoiceType::Custom);

        expect($third->number)->toBe('CM-INV-'.now()->year.'-0003');
    });

    it('returns the existing invoice instead of issuing a duplicate', function () {
        $first = app(GenerateInvoice::class)->handle($this->booking, InvoiceType::Deposit);
        $again = app(GenerateInvoice::class)->handle($this->booking, InvoiceType::Deposit);

        expect($again->id)->toBe($first->id)
            ->and(Invoice::count())->toBe(1);
    });
});

describe('the deposit and final pair', function () {
    it('asks for the deposit amount, showing the full scope', function () {
        $deposit = app(GenerateInvoice::class)->handle($this->booking, InvoiceType::Deposit);

        expect($deposit->subtotal_cents)->toBeMoney(815000)     // full contract
            ->and($deposit->total_cents)->toBeMoney(244500)     // 30% due now
            ->and($deposit->items)->toHaveCount(3);             // package, add-on, deduction
    });

    it('charges the balance and deducts the deposit', function () {
        $deposit = app(GenerateInvoice::class)->handle($this->booking, InvoiceType::Deposit);
        $final = app(GenerateInvoice::class)->handle($this->booking->fresh(), InvoiceType::Final);

        expect($final->total_cents)->toBeMoney(815000 - 244500)
            ->and($final->depositInvoice->id)->toBe($deposit->id);
    });

    it('reconciles: deposit plus final equals the contract', function () {
        // The studio can hand a client both documents and the arithmetic holds.
        $deposit = app(GenerateInvoice::class)->handle($this->booking, InvoiceType::Deposit);
        $final = app(GenerateInvoice::class)->handle($this->booking->fresh(), InvoiceType::Final);

        expect($deposit->total_cents->plus($final->total_cents))
            ->toBeMoney($this->booking->estimated_total_cents->cents);
    });

    it('captures add-on prices as they were at booking time', function () {
        $invoice = app(GenerateInvoice::class)->handle($this->booking, InvoiceType::Deposit);

        // The studio raises prices afterwards.
        AddOn::query()->update(['price_cents' => 999999]);
        $this->package->update(['price_cents' => 999999]);

        $line = $invoice->fresh('items')->items->firstWhere('description', 'Extra hour');

        expect($line->unit_price_cents)->toBeMoney(45000)
            ->and($line->total_cents)->toBeMoney(135000);
    });

    it('copies client details rather than referencing the booking', function () {
        $invoice = app(GenerateInvoice::class)->handle($this->booking, InvoiceType::Deposit);

        // A correction to the booking must not rewrite an issued invoice.
        $this->booking->update(['partner_one_name' => 'Corrected', 'email' => 'new@example.test']);

        expect($invoice->fresh()->client_name)->toBe('Aisyah & Danial')
            ->and($invoice->fresh()->client_email)->toBe('aisyah@example.test');
    });
});

describe('locking', function () {
    it('is editable while a draft', function () {
        $invoice = app(GenerateInvoice::class)->handle($this->booking, InvoiceType::Deposit);

        expect($invoice->isEditable())->toBeTrue()
            ->and($invoice->status)->toBe(InvoiceStatus::Draft);
    });

    it('locks once issued', function () {
        $invoice = app(IssueInvoice::class)->handle(
            app(GenerateInvoice::class)->handle($this->booking, InvoiceType::Deposit)
        );

        expect($invoice->isEditable())->toBeFalse()
            ->and($invoice->locked_at)->not->toBeNull()
            ->and($invoice->status)->toBe(InvoiceStatus::Sent);
    });

    it('does not move the lock timestamp on a second issue', function () {
        $invoice = app(IssueInvoice::class)->handle(
            app(GenerateInvoice::class)->handle($this->booking, InvoiceType::Deposit)
        );
        $lockedAt = $invoice->locked_at;

        $invoice = app(IssueInvoice::class)->handle($invoice);

        expect($invoice->locked_at->timestamp)->toBe($lockedAt->timestamp);
    });
});

describe('payments', function () {
    beforeEach(function () {
        $this->invoice = app(IssueInvoice::class)->handle(
            app(GenerateInvoice::class)->handle($this->booking, InvoiceType::Deposit)
        );
    });

    it('marks an invoice paid once fully settled', function () {
        app(RecordPayment::class)->handle($this->invoice, [
            'amount_cents' => $this->invoice->total_cents->cents,
            'paid_at' => today(),
            'method' => PaymentMethod::BankTransfer,
        ]);

        expect($this->invoice->fresh()->status)->toBe(InvoiceStatus::Paid);
    });

    it('leaves a part-paid invoice as sent, still owing the remainder', function () {
        app(RecordPayment::class)->handle($this->invoice, [
            'amount_cents' => 100000,
            'paid_at' => today(),
            'method' => PaymentMethod::Cash,
        ]);

        $invoice = $this->invoice->fresh('payments');

        expect($invoice->status)->toBe(InvoiceStatus::Sent)
            ->and($invoice->paid())->toBeMoney(100000)
            ->and($invoice->outstanding())->toBeMoney(144500);
    });

    it('un-settles an invoice when a payment is removed', function () {
        $payment = app(RecordPayment::class)->handle($this->invoice, [
            'amount_cents' => $this->invoice->total_cents->cents,
            'paid_at' => today(),
            'method' => PaymentMethod::BankTransfer,
        ]);
        expect($this->invoice->fresh()->status)->toBe(InvoiceStatus::Paid);

        app(RecordPayment::class)->remove($payment);

        // Otherwise a mistaken payment leaves the invoice permanently "paid"
        // and the receivables figure permanently wrong.
        expect($this->invoice->fresh()->status)->toBe(InvoiceStatus::Sent);
    });

    it('never reports a negative balance on an overpayment', function () {
        app(RecordPayment::class)->handle($this->invoice, [
            'amount_cents' => $this->invoice->total_cents->cents + 50000,
            'paid_at' => today(),
            'method' => PaymentMethod::BankTransfer,
        ]);

        expect($this->invoice->fresh('payments')->outstanding())->toBeMoney(0);
    });
});

describe('overdue', function () {
    it('is computed from the due date, not stored', function () {
        $invoice = Invoice::factory()->overdue(45)->create();

        expect($invoice->isOverdue())->toBeTrue()
            ->and($invoice->status)->toBe(InvoiceStatus::Sent)   // no "overdue" status exists
            ->and($invoice->daysOverdue())->toBe(45);
    });

    it('is not overdue once settled, however late', function () {
        $invoice = Invoice::factory()->overdue(90)->totalling(100000)->create();

        app(RecordPayment::class)->handle($invoice, [
            'amount_cents' => 100000,
            'paid_at' => today(),
            'method' => PaymentMethod::BankTransfer,
        ]);

        expect($invoice->fresh('payments')->isOverdue())->toBeFalse();
    });

    it('buckets by age', function (int $days, string $bucket) {
        $invoice = Invoice::factory()->overdue($days)->create();

        expect($invoice->ageingBucket())->toBe($bucket);
    })->with([
        [10, '0-30'],
        [30, '0-30'],
        [45, '31-60'],
        [90, '60+'],
    ]);

    it('has no bucket when not overdue', function () {
        expect(Invoice::factory()->sent()->create(['due_at' => today()->addWeek()])->ageingBucket())
            ->toBeNull();
    });
});

describe('the PDF', function () {
    it('renders and stores against the record', function () {
        Storage::fake('local');

        $invoice = app(GenerateInvoice::class)->handle($this->booking, InvoiceType::Deposit);
        $path = app(RenderInvoicePdf::class)->handle($invoice);

        Storage::disk('local')->assertExists($path);

        expect($invoice->fresh()->pdf_path)->toBe($path)
            ->and($path)->toContain($invoice->number);
    });

    it('does not change the number or the prices when re-rendered', function () {
        Storage::fake('local');

        $invoice = app(IssueInvoice::class)->handle(
            app(GenerateInvoice::class)->handle($this->booking, InvoiceType::Deposit)
        );

        $number = $invoice->number;
        $total = $invoice->total_cents->cents;

        app(RenderInvoicePdf::class)->handle($invoice);
        app(RenderInvoicePdf::class)->handle($invoice->fresh());

        $invoice = $invoice->fresh();

        expect($invoice->number)->toBe($number)
            ->and($invoice->total_cents)->toBeMoney($total)
            ->and(Invoice::count())->toBe(1);
    });
});

describe('the client download link', function () {
    it('is refused without a signature', function () {
        $invoice = Invoice::factory()->sent()->create();

        // A guessable /invoices/1/download must not serve anything.
        $this->get("/invoices/{$invoice->id}/download")->assertForbidden();
    });

    it('works with a valid signature and no login', function () {
        Storage::fake('local');
        $invoice = app(GenerateInvoice::class)->handle($this->booking, InvoiceType::Deposit);

        $this->get($invoice->downloadUrl())
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    });

    it('stops working after the link expires', function () {
        $invoice = Invoice::factory()->sent()->create();
        $url = $invoice->downloadUrl(days: 7);

        $this->travel(8)->days();

        $this->get($url)->assertForbidden();
    });
});

describe('sending', function () {
    it('issues, attaches the PDF and queues the email', function () {
        Mail::fake();
        Storage::fake('local');

        $invoice = app(GenerateInvoice::class)->handle($this->booking, InvoiceType::Deposit);
        app(SendInvoice::class)->handle($invoice);

        Mail::assertQueued(InvoiceMail::class, fn ($mail) => $mail->hasTo('aisyah@example.test'));

        $invoice = $invoice->fresh();

        expect($invoice->status)->toBe(InvoiceStatus::Sent)
            ->and($invoice->locked_at)->not->toBeNull()
            // The attachment has to exist before the mailable is queued.
            ->and($invoice->pdf_path)->not->toBeNull();
    });

    it('refuses to send without a client email', function () {
        $invoice = Invoice::factory()->create(['client_email' => null]);

        expect(fn () => app(SendInvoice::class)->handle($invoice))
            ->toThrow(RuntimeException::class);
    });
});

describe('queued work', function () {
    it('queues the PDF render when an invoice is issued', function () {
        Illuminate\Support\Facades\Queue::fake();

        app(IssueInvoice::class)->handle(
            app(GenerateInvoice::class)->handle($this->booking, InvoiceType::Deposit)
        );

        // The request must never wait on PDF generation.
        Illuminate\Support\Facades\Queue::assertPushed(App\Jobs\RenderInvoicePdfJob::class);
    });

    it('renders on demand when the queued file is not there yet', function () {
        Storage::fake('local');

        $invoice = app(GenerateInvoice::class)->handle($this->booking, InvoiceType::Deposit);
        expect($invoice->pdf_path)->toBeNull();

        // A client clicking the link before the worker ran must still get
        // their invoice, not a 404.
        $this->get($invoice->downloadUrl())->assertOk();

        expect($invoice->fresh()->pdf_path)->not->toBeNull();
    });
});
