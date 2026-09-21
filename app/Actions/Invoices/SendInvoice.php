<?php

declare(strict_types=1);

namespace App\Actions\Invoices;

use App\Mail\InvoiceMail;
use App\Models\Invoice;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

/**
 * Emails an invoice to the client.
 *
 * Issues it first (locking the figures), then renders the PDF synchronously —
 * the attachment has to exist before the mailable is queued, or the client
 * receives an email with nothing attached.
 */
class SendInvoice
{
    public function __construct(
        private readonly IssueInvoice $issue,
        private readonly RenderInvoicePdf $render,
    ) {}

    public function handle(Invoice $invoice): Invoice
    {
        if (blank($invoice->client_email)) {
            throw new RuntimeException('This invoice has no client email address.');
        }

        $invoice = $this->issue->handle($invoice);

        // Rendered here, not queued: the queued mailable serialises the
        // invoice and reads pdf_path when it runs, so the file must already
        // be on disk.
        $this->render->handle($invoice);

        Mail::to($invoice->client_email)->queue(new InvoiceMail($invoice->refresh()));

        return $invoice;
    }
}
