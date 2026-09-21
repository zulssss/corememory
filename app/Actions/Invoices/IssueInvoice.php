<?php

declare(strict_types=1);

namespace App\Actions\Invoices;

use App\Enums\InvoiceStatus;
use App\Jobs\RenderInvoicePdfJob;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

/**
 * Moves an invoice out of draft and locks it.
 *
 * Locking is the point. Once a client has a PDF, the numbers behind it must
 * stop moving — otherwise the studio and the client are looking at two
 * different documents with the same number on them.
 */
class IssueInvoice
{
    public function handle(Invoice $invoice): Invoice
    {
        return DB::transaction(function () use ($invoice): Invoice {
            if ($invoice->status === InvoiceStatus::Draft) {
                $invoice->forceFill([
                    'status' => InvoiceStatus::Sent,
                    'issued_at' => $invoice->issued_at ?? today(),
                    'locked_at' => now(),
                ])->save();
            }

            $invoice = $invoice->fresh(['items', 'payments', 'booking']);

            /*
             * Render the PDF on the QUEUE, not in this request.
             *
             * An issued invoice will be looked at, so having the file ready
             * beforehand means the download is instant. The download
             * controller still renders on demand if the job has not run or the
             * file was cleaned up by a deploy — this is an optimisation, not
             * the only path.
             *
             * afterCommit: the job must not start before this transaction
             * commits, or the worker reads an invoice that does not exist yet.
             */
            RenderInvoicePdfJob::dispatch($invoice)->afterCommit();

            return $invoice;
        });
    }
}
