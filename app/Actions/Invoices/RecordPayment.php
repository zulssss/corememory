<?php

declare(strict_types=1);

namespace App\Actions\Invoices;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * Records money actually received against an invoice.
 *
 * Also flips the invoice to Paid once the payments cover the total — which is
 * why payments go through here rather than being created directly. A payment
 * recorded without updating the status leaves the invoice showing as overdue
 * forever, and the receivables figure permanently wrong.
 */
class RecordPayment
{
    /**
     * @param  array{amount_cents: int, paid_at: string|\DateTimeInterface, method: string|PaymentMethod, reference?: string|null, notes?: string|null}  $data
     */
    public function handle(Invoice $invoice, array $data): Payment
    {
        return DB::transaction(function () use ($invoice, $data): Payment {
            $payment = $invoice->payments()->create([
                'amount_cents' => (int) $data['amount_cents'],
                'paid_at' => $data['paid_at'],
                'method' => $data['method'] instanceof PaymentMethod
                    ? $data['method']
                    : PaymentMethod::from($data['method']),
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $invoice->load('payments');

            // A fully-settled invoice is Paid. A partly-paid one stays Sent,
            // so it still shows in receivables for what is actually left.
            if ($invoice->isSettled() && $invoice->status !== InvoiceStatus::Cancelled) {
                $invoice->forceFill(['status' => InvoiceStatus::Paid])->save();
            }

            return $payment;
        });
    }

    /** Removing a payment must be able to un-settle an invoice too. */
    public function remove(Payment $payment): void
    {
        DB::transaction(function () use ($payment): void {
            $invoice = $payment->invoice;
            $payment->delete();

            $invoice->load('payments');

            if (! $invoice->isSettled() && $invoice->status === InvoiceStatus::Paid) {
                $invoice->forceFill(['status' => InvoiceStatus::Sent])->save();
            }
        });
    }
}
