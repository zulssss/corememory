<?php

declare(strict_types=1);

namespace App\Filament\Actions;

use App\Models\Booking;
use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exports the current, filtered booking list to CSV.
 *
 * Streams row by row rather than building the whole file in memory, so a
 * studio with years of bookings can still export without exhausting PHP's
 * memory limit.
 *
 * Deliberately not Filament's queued exporter: that needs its own migrations
 * and a running queue worker, and "download the list I am looking at" should
 * not depend on either.
 */
class ExportBookingsCsv
{
    public static function make(): Action
    {
        return Action::make('exportCsv')
            ->label('Export to CSV')
            ->icon('heroicon-o-arrow-down-tray')
            ->action(function ($livewire): StreamedResponse {
                // Respects whatever filters and sorting are currently applied.
                $query = $livewire->getFilteredSortedTableQuery()
                    ->with(['package', 'dates', 'addOns']);

                return self::stream($query);
            });
    }

    private static function stream(Builder $query): StreamedResponse
    {
        $filename = 'corememory-bookings-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'wb');

            fputcsv($handle, [
                'Reference', 'Status', 'Couple', 'Email', 'Phone', 'Guests',
                'Source', 'Package', 'Dates', 'Add-ons',
                'Subtotal (RM)', 'Add-ons (RM)', 'Total (RM)', 'Deposit (RM)',
                'Enquired',
            ]);

            // chunkById keeps memory flat regardless of how many rows there are.
            $query->chunkById(200, function ($bookings) use ($handle): void {
                foreach ($bookings as $booking) {
                    fputcsv($handle, self::row($booking));
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return list<string|float|int|null> */
    private static function row(Booking $booking): array
    {
        return [
            $booking->reference,
            $booking->status->label(),
            $booking->coupleNames(),
            $booking->email,
            $booking->phone,
            $booking->guest_count,
            $booking->source?->label(),
            $booking->package?->name,

            $booking->dates
                ->map(fn ($d) => $d->event_date->format('Y-m-d').' '.$d->session_slot->value)
                ->implode(' | '),

            $booking->addOns
                ->map(fn ($a) => $a->name.($a->pivot->qty > 1 ? ' x'.$a->pivot->qty : ''))
                ->implode(' | '),

            // Major units for the spreadsheet — stored as cents, converted
            // only here, at the edge.
            $booking->subtotal_cents->toRinggit(),
            $booking->addons_total_cents->toRinggit(),
            $booking->estimated_total_cents->toRinggit(),
            $booking->deposit_cents->toRinggit(),

            $booking->created_at?->format('Y-m-d H:i'),
        ];
    }
}
