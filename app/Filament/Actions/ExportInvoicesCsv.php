<?php

declare(strict_types=1);

namespace App\Filament\Actions;

use App\Models\Invoice;
use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exports the current, filtered invoice list to CSV, for the accountant.
 *
 * Streams row by row so a studio with years of invoices can export without
 * exhausting the memory limit.
 */
class ExportInvoicesCsv
{
    public static function make(): Action
    {
        return Action::make('exportInvoicesCsv')
            ->label('Export to CSV')
            ->icon('heroicon-o-arrow-down-tray')
            ->action(fn ($livewire): StreamedResponse => self::stream(
                $livewire->getFilteredSortedTableQuery()->with(['booking', 'payments'])
            ));
    }

    private static function stream(Builder $query): StreamedResponse
    {
        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'wb');

            fputcsv($handle, [
                'Number', 'Type', 'Status', 'Client', 'Email', 'Booking reference',
                'Issued', 'Due', 'Subtotal (RM)', 'Deductions (RM)', 'Total (RM)',
                'Paid (RM)', 'Outstanding (RM)', 'Days overdue',
            ]);

            $query->chunkById(200, function ($invoices) use ($handle): void {
                foreach ($invoices as $invoice) {
                    fputcsv($handle, self::row($invoice));
                }
            });

            fclose($handle);
        }, 'corememory-invoices-'.now()->format('Y-m-d').'.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return list<string|float|int|null> */
    private static function row(Invoice $invoice): array
    {
        return [
            $invoice->number,
            $invoice->type->label(),
            $invoice->isOverdue() ? 'Overdue' : $invoice->status->label(),
            $invoice->client_name,
            $invoice->client_email,
            $invoice->booking?->reference,
            $invoice->issued_at?->format('Y-m-d'),
            $invoice->due_at?->format('Y-m-d'),

            // Major units for the spreadsheet; stored as cents, converted
            // only here at the edge.
            $invoice->subtotal_cents->toRinggit(),
            $invoice->discount_cents->toRinggit(),
            $invoice->total_cents->toRinggit(),
            $invoice->paid()->toRinggit(),
            $invoice->outstanding()->toRinggit(),
            $invoice->daysOverdue() ?: null,
        ];
    }
}
