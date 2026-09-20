<?php

declare(strict_types=1);

namespace App\Actions\Bookings;

use App\Mail\BookingReceivedMail;
use App\Mail\BookingSubmittedMail;
use App\Models\Booking;
use App\Support\Settings;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sends the two emails that follow a booking request.
 *
 * Kept OUT of CreateBooking on purpose: the seeder and any future admin-side
 * booking entry use the same Action to create a booking, and neither should
 * email anyone. Notifying is a separate decision from recording.
 *
 * Both mailables are queued. A mail failure must never lose a booking that is
 * already safely in the database, so failures are logged rather than thrown.
 */
class SendBookingNotifications
{
    public function handle(Booking $booking): void
    {
        $booking->loadMissing(['dates', 'addOns', 'package']);

        try {
            Mail::to($booking->email)->queue(new BookingReceivedMail($booking));
        } catch (\Throwable $e) {
            Log::error('Failed to queue client booking email', [
                'reference' => $booking->reference,
                'error' => $e->getMessage(),
            ]);
        }

        $studioAddress = Settings::get('contact.email')
            ?? config('mail.studio_address')
            ?? config('mail.from.address');

        if (blank($studioAddress)) {
            return;
        }

        try {
            Mail::to($studioAddress)->queue(new BookingSubmittedMail($booking));
        } catch (\Throwable $e) {
            Log::error('Failed to queue studio booking email', [
                'reference' => $booking->reference,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
