<?php

declare(strict_types=1);

namespace App\Actions\Invoices;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Sequence;
use App\Support\Settings;
use App\ValueObjects\Money;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Builds an invoice from a booking.
 *
 * SNAPSHOT, NOT A VIEW. Line items are copied at the prices captured when the
 * couple booked, and client details are copied too. Editing the booking later,
 * or raising a package price, must never rewrite an invoice that has already
 * been sent.
 *
 * Deposit and final invoices reconcile with each other:
 *
 *   DEPOSIT                                 FINAL
 *   Package                    6,800.00     Package                    6,800.00
 *   Extra hour x3              1,350.00     Extra hour x3              1,350.00
 *   Less: balance after event -5,705.00     Less: deposit invoiced    -2,445.00
 *   ------------------------------------    ------------------------------------
 *   Due now                    2,445.00     Due                        5,705.00
 *
 * The two always add back to the contract value, so the studio can hand a
 * client both documents and the arithmetic holds.
 */
class GenerateInvoice
{
    /**
     * Idempotent per booking and type: asking twice for the deposit invoice
     * returns the one that already exists rather than issuing a duplicate
     * number. (Pattern borrowed from the ceritaconvo repo; the numbering
     * underneath is not — see allocateNumber().)
     */
    public function handle(
        Booking $booking,
        InvoiceType $type = InvoiceType::Deposit,
        ?int $dueInDays = null,
    ): Invoice {
        $existing = $booking->invoices()
            ->where('type', $type)
            ->whereNot('status', InvoiceStatus::Cancelled)
            ->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($booking, $type, $dueInDays): Invoice {
            $booking->loadMissing(['package', 'addOns', 'dates']);

            $deposit = $booking->deposit_cents;
            $contract = $booking->estimated_total_cents;

            $depositInvoice = $type === InvoiceType::Final
                ? $booking->invoices()->where('type', InvoiceType::Deposit)->first()
                : null;

            $invoice = Invoice::create([
                'number' => $this->allocateNumber(),
                'booking_id' => $booking->getKey(),
                'type' => $type,

                // Copied, not referenced.
                'client_name' => $booking->coupleNames(),
                'client_email' => $booking->email,
                'client_phone' => $booking->phone,

                'issued_at' => today(),
                'due_at' => today()->addDays($dueInDays ?? $this->defaultDueDays($type)),

                'status' => InvoiceStatus::Draft,
                'deposit_of_invoice_id' => $depositInvoice?->getKey(),
            ]);

            $this->addScopeLines($invoice, $booking);

            // The deduction that makes this invoice ask for the right amount.
            match ($type) {
                InvoiceType::Deposit => $this->addDeduction(
                    $invoice,
                    __('invoice.lines.balance_after_event'),
                    $contract->minus($deposit),
                ),
                InvoiceType::Final => $depositInvoice
                    ? $this->addDeduction(
                        $invoice,
                        __('invoice.lines.deposit_invoiced', ['number' => $depositInvoice->number]),
                        $depositInvoice->total_cents,
                    )
                    : null,
                InvoiceType::Custom => null,
            };

            return $this->recalculate($invoice);
        });
    }

    /**
     * A standalone invoice with no booking behind it — a walk-in, a print
     * order, a re-shoot.
     *
     * @param  list<array{description: string, qty: int, unit_price_cents: int}>  $lines
     */
    public function custom(array $clientDetails, array $lines, ?int $dueInDays = null): Invoice
    {
        if ($lines === []) {
            throw new RuntimeException('A custom invoice needs at least one line item.');
        }

        return DB::transaction(function () use ($clientDetails, $lines, $dueInDays): Invoice {
            $invoice = Invoice::create([
                'number' => $this->allocateNumber(),
                'type' => InvoiceType::Custom,
                'client_name' => $clientDetails['name'],
                'client_email' => $clientDetails['email'] ?? null,
                'client_phone' => $clientDetails['phone'] ?? null,
                'client_address' => $clientDetails['address'] ?? null,
                'issued_at' => today(),
                'due_at' => today()->addDays($dueInDays ?? 14),
                'status' => InvoiceStatus::Draft,
            ]);

            foreach ($lines as $index => $line) {
                $invoice->items()->create([
                    'description' => $line['description'],
                    'qty' => $qty = (int) ($line['qty'] ?? 1),
                    'unit_price_cents' => $unit = (int) $line['unit_price_cents'],
                    'total_cents' => $unit * $qty,
                    'sort_order' => $index,
                ]);
            }

            return $this->recalculate($invoice);
        });
    }

    /**
     * Recompute subtotal and total from the line items.
     *
     * Called after generation and whenever the studio edits a draft, so the
     * stored figures and the printed lines can never disagree.
     */
    public function recalculate(Invoice $invoice): Invoice
    {
        $invoice->load('items');

        // Positive lines are the scope; negative lines are deductions.
        $positive = $invoice->items->sum(fn ($item) => max(0, $item->total_cents->cents));
        $negative = $invoice->items->sum(fn ($item) => min(0, $item->total_cents->cents));

        $invoice->forceFill([
            'subtotal_cents' => $positive,
            'discount_cents' => abs($negative),
            'total_cents' => $positive + $negative,
        ])->save();

        return $invoice->fresh(['items', 'booking', 'payments']);
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /** The package and each add-on, at the price captured when they booked. */
    private function addScopeLines(Invoice $invoice, Booking $booking): void
    {
        $order = 0;

        if ($booking->package) {
            $invoice->items()->create([
                'description' => $booking->package->name
                    .$this->datesSuffix($booking),
                'qty' => 1,
                'unit_price_cents' => $booking->subtotal_cents->cents,
                'total_cents' => $booking->subtotal_cents->cents,
                'sort_order' => $order++,
            ]);
        }

        foreach ($booking->addOns as $addOn) {
            $qty = (int) $addOn->pivot->qty;

            $invoice->items()->create([
                'description' => $addOn->name,
                'qty' => $qty,
                // The price THEN, not today's price.
                'unit_price_cents' => (int) $addOn->pivot->price_cents_at_booking,
                'total_cents' => (int) $addOn->pivot->line_total_cents,
                'sort_order' => $order++,
            ]);
        }
    }

    private function addDeduction(Invoice $invoice, string $description, Money $amount): void
    {
        if ($amount->isZero()) {
            return;
        }

        $invoice->items()->create([
            'description' => $description,
            'qty' => 1,
            'unit_price_cents' => -$amount->cents,
            'total_cents' => -$amount->cents,
            'sort_order' => 900,   // deductions always print last
        ]);
    }

    /** " — 14 Feb 2026" so the invoice says what it is for. */
    private function datesSuffix(Booking $booking): string
    {
        $dates = $booking->dates
            ->map(fn ($d) => $d->event_date->translatedFormat('d M Y'))
            ->implode(', ');

        return $dates === '' ? '' : ' — '.$dates;
    }

    private function defaultDueDays(InvoiceType $type): int
    {
        // A deposit confirms a date, so it is due quickly. A final invoice
        // follows the event and gets the studio's standard terms.
        return $type === InvoiceType::Deposit ? 7 : 14;
    }

    /**
     * CM-INV-2026-0001.
     *
     * From the locked sequences row inside this transaction. Deliberately NOT
     * count() + 1: that reuses a number after any delete, and hands two
     * simultaneous requests the same one. The brief requires "sequential,
     * never reused", and only a locked counter delivers that.
     */
    private function allocateNumber(): string
    {
        $year = (int) now()->year;
        $number = Sequence::next('invoice', $year);

        return sprintf(
            '%s-INV-%d-%s',
            Settings::get('invoice.prefix') ?? config('booking.invoice_prefix'),
            $year,
            str_pad((string) $number, (int) config('booking.sequence_padding'), '0', STR_PAD_LEFT),
        );
    }
}
