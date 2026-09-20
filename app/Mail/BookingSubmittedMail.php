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

/** Sent to the studio, with a direct link to the admin record. */
class BookingSubmittedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Booking $booking) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.studio.subject', [
                'reference' => $this->booking->reference,
                'couple' => $this->booking->coupleNames(),
            ]),
            replyTo: [$this->booking->email],
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.booking-submitted', with: [
            'adminUrl' => url('/admin/bookings/'.$this->booking->getKey().'/edit'),
        ]);
    }
}
