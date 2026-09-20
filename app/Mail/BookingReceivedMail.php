<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the couple the moment their request lands.
 *
 * Queued: the web request must never wait on an SMTP handshake. A slow mail
 * server would otherwise make the booking form feel broken at exactly the
 * moment a couple is deciding whether to trust the studio.
 */
class BookingReceivedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Booking $booking) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.client.subject', ['reference' => $this->booking->reference]),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.booking-received');
    }
}
