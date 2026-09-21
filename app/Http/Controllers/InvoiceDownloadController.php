<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Invoices\RenderInvoicePdf;
use App\Models\Invoice;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceDownloadController extends Controller
{
    /**
     * Serves an invoice PDF from a signed, expiring link.
     *
     * No authentication, by design — a couple must be able to open this
     * straight from an email without an account. Security comes from the
     * signature: the route is wrapped in Laravel's `signed` middleware, so the
     * URL cannot be forged or produced by walking invoice ids, and it stops
     * working after its expiry.
     *
     * The file lives on the private 'local' disk and is streamed through PHP,
     * never exposed under public/ where a path guess would find it.
     */
    public function __invoke(Invoice $invoice, RenderInvoicePdf $render): StreamedResponse
    {
        // A queued render that hasn't run yet, or a file cleaned up by a
        // deploy, shouldn't give the client a 404 — regenerate on demand.
        // Prices and the number come from the record, so a re-render is
        // byte-for-byte the same document.
        if ($invoice->pdf_path === null || ! Storage::disk('local')->exists($invoice->pdf_path)) {
            $render->handle($invoice);
            $invoice->refresh();
        }

        return Storage::disk('local')->download(
            $invoice->pdf_path,
            "{$invoice->number}.pdf",
            ['Content-Type' => 'application/pdf'],
        );
    }
}
