<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Bookings\ReleaseBooking;
use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Console\Command;

/**
 * Auto-cancels pending enquiries nobody has actioned.
 *
 * Without this, a popular Saturday accumulates stale "another enquiry is
 * pending" notices forever, and the tentative signal stops meaning anything.
 *
 * Shape borrowed from the ceritaconvo repo's bookings:expire-stale, adapted to
 * our 14-day window (config/booking.php). Cancelled bookings are kept, not
 * deleted — the studio can revive one, and the dashboard still needs them for
 * the conversion-rate funnel.
 *
 * Scheduled daily in routes/console.php.
 */
class ExpireStaleBookings extends Command
{
    protected $signature = 'bookings:expire-stale {--dry-run : List what would be cancelled without changing anything}';

    protected $description = 'Cancel pending booking enquiries that have had no response';

    public function handle(ReleaseBooking $release): int
    {
        $days = (int) config('booking.pending_lapse_days');
        $cutoff = now()->subDays($days);

        $stale = Booking::query()
            ->where('status', BookingStatus::Pending)
            ->where('created_at', '<=', $cutoff)
            ->with('dates')
            ->get();

        if ($stale->isEmpty()) {
            $this->info('No stale enquiries.');

            return self::SUCCESS;
        }

        foreach ($stale as $booking) {
            if ($this->option('dry-run')) {
                $this->line("  would cancel {$booking->reference} ({$booking->created_at->diffForHumans()})");

                continue;
            }

            // Through the Action, so any holds are released too. Pending does
            // not normally hold — but if the studio ever makes pending a
            // blocking status, this still does the right thing.
            $release->handle(
                $booking,
                BookingStatus::Cancelled,
                reason: __('booking.notes.lapsed', ['days' => $days]),
            );

            $booking->forceFill(['lapsed_at' => now()])->save();
        }

        $verb = $this->option('dry-run') ? 'Would cancel' : 'Cancelled';
        $this->info("{$verb} {$stale->count()} stale enquiry(ies) older than {$days} days.");

        return self::SUCCESS;
    }
}
