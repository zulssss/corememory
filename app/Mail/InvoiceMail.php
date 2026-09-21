<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sends an invoice to the client, with the PDF attached.
 *
 * ONLY ever sent by an explicit admin action — never automatically on
 * generation. The studio decides when a client sees an invoice.
 */
class InvoiceMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invoice $invoice,
        public int $linkDays = 30,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('invoice.mail.subject', ['number' => $this->invoice->number]),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.invoice', with: [
            'downloadUrl' => $this->invoice->downloadUrl($this->linkDays),
        ]);
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        if ($this->invoice->pdf_path === null) {
            return [];
        }

        return [
            Attachment::fromStorageDisk('local', $this->invoice->pdf_path)
                ->as("{$this->invoice->number}.pdf")
                ->withMime('application/pdf'),
        ];
    }
}
