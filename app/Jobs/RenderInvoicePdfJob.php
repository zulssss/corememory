<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Invoices\RenderInvoicePdf;
use App\Models\Invoice;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Renders an invoice PDF on the queue.
 *
 * PDF generation takes a second or two. The brief is explicit that the request
 * must never wait on it, so the admin gets an immediate response and the file
 * appears shortly after.
 */
class RenderInvoicePdfJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public Invoice $invoice) {}

    public function handle(RenderInvoicePdf $render): void
    {
        $render->handle($this->invoice);
    }
}
