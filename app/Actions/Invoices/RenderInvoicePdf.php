<?php

declare(strict_types=1);

namespace App\Actions\Invoices;

use App\Models\Invoice;
use App\Support\Settings;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * Renders an invoice to PDF and stores it against the record.
 *
 * WHY DOMPDF, not Browsershot:
 * An invoice is a letterhead and a bordered table. It needs no flexbox, no
 * grid, and no JavaScript. dompdf is pure PHP, so the VPS needs no headless
 * Chromium — that is ~400 MB of dependency plus a whole class of queue-worker
 * failures (zombie processes, sandbox flags, missing shared libraries) for a
 * document that renders perfectly well without it.
 *
 * Stored on the 'local' disk, which is NOT publicly reachable. Clients get a
 * signed, expiring URL instead, so a PDF cannot be found by guessing a path.
 */
class RenderInvoicePdf
{
    public function handle(Invoice $invoice): string
    {
        $invoice->loadMissing(['items', 'payments', 'booking.dates', 'depositInvoice']);

        $pdf = Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'studio' => [
                'name' => Settings::get('invoice.registered_name') ?? __('site.brand.legal_name'),
                'ssm' => Settings::get('invoice.ssm_number'),
                'address' => Settings::get('invoice.address'),
                'email' => Settings::get('contact.email'),
                'phone' => Settings::get('contact.phone'),
                'bank_name' => Settings::get('invoice.bank_name'),
                'bank_account_name' => Settings::get('invoice.bank_account_name'),
                'bank_account_number' => Settings::get('invoice.bank_account_number'),
                'payment_terms' => Settings::get('booking.payment_terms'),
                'cancellation_policy' => Settings::get('booking.cancellation_policy'),
            ],
        ])->setPaper('a4');

        // Keyed by invoice NUMBER, not id — regenerating overwrites the same
        // file for the same invoice rather than littering the disk.
        $path = "invoices/{$invoice->number}.pdf";

        Storage::disk('local')->put($path, $pdf->output());

        // Only the path changes. The number and the captured prices are never
        // touched by a re-render.
        $invoice->forceFill(['pdf_path' => $path])->save();

        return $path;
    }
}
