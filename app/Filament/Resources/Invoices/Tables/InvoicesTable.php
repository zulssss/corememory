<?php

declare(strict_types=1);

namespace App\Filament\Resources\Invoices\Tables;

use App\Actions\Invoices\RenderInvoicePdf;
use App\Actions\Invoices\SendInvoice;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Filament\Actions\ExportInvoicesCsv;
use App\Models\Invoice;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // payments are needed by the Paid/Outstanding columns; without
            // this every row fires its own query.
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['booking', 'payments']))
            ->defaultSort('issued_at', 'desc')
            ->columns([
                TextColumn::make('number')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->copyable(),

                TextColumn::make('client_name')
                    ->label('Client')
                    ->searchable()
                    ->description(fn (Invoice $record) => $record->booking?->reference),

                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (InvoiceType $state) => $state->label()),

                TextColumn::make('total_cents')
                    ->label('Total')
                    ->formatStateUsing(fn (Invoice $record) => $record->total_cents->formatCompact())
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('paid')
                    ->label('Paid')
                    ->state(fn (Invoice $record) => $record->paid()->formatCompact())
                    ->alignEnd()
                    ->color(fn (Invoice $record) => $record->isSettled() ? 'success' : 'gray'),

                TextColumn::make('outstanding')
                    ->label('Outstanding')
                    ->state(fn (Invoice $record) => $record->outstanding()->formatCompact())
                    ->alignEnd()
                    ->weight(fn (Invoice $record) => $record->isOverdue() ? 'bold' : 'normal')
                    ->color(fn (Invoice $record) => $record->isOverdue() ? 'danger' : 'gray'),

                TextColumn::make('status')
                    ->badge()
                    // Overdue is computed, not stored — so the badge has to
                    // ask the model rather than read the column.
                    ->state(fn (Invoice $record) => $record->isOverdue()
                        ? __('invoice.overdue')
                        : $record->status->label())
                    ->color(fn (Invoice $record) => $record->isOverdue() ? 'danger' : $record->status->color())
                    ->sortable(),

                TextColumn::make('due_at')
                    ->label('Due')
                    ->date('d M Y')
                    ->sortable()
                    ->description(fn (Invoice $record) => $record->isOverdue()
                        ? $record->daysOverdue().' days overdue'
                        : null),
            ])
            ->filters([
                SelectFilter::make('status')->options(InvoiceStatus::options())->multiple(),
                SelectFilter::make('type')->options(InvoiceType::options())->multiple(),

                Filter::make('overdue')
                    ->label('Overdue only')
                    ->query(fn (Builder $query) => $query->overdue())
                    ->toggle(),

                Filter::make('issued')
                    ->schema([
                        DatePicker::make('from')->label('Issued from')->native(false),
                        DatePicker::make('until')->label('Issued until')->native(false),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('issued_at', '>=', $d))
                        ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('issued_at', '<=', $d))),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    self::downloadAction(),
                    self::sendAction(),
                    self::copyLinkAction(),
                ]),
            ])
            ->headerActions([
                ExportInvoicesCsv::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ])
            ->emptyStateHeading('No invoices yet')
            ->emptyStateDescription('Generate one from a booking, or create a custom invoice.');
    }

    /** Renders on demand and streams the PDF straight to the browser. */
    public static function downloadAction(): Action
    {
        return Action::make('download')
            ->label('Download PDF')
            ->icon('heroicon-o-arrow-down-tray')
            ->action(function (Invoice $record, RenderInvoicePdf $render) {
                $render->handle($record);

                // Storage::path rather than storage_path(): the local disk
                // root moved to storage/app/private in Laravel 11, and asking
                // the disk means this keeps working wherever it points.
                return response()->download(
                    Storage::disk('local')->path($record->refresh()->pdf_path),
                    "{$record->number}.pdf",
                );
            });
    }

    /**
     * Emails the invoice with the PDF attached.
     *
     * Requires confirmation because it is outward-facing and irreversible —
     * and because sending locks the invoice.
     */
    public static function sendAction(): Action
    {
        return Action::make('send')
            ->label('Send to client')
            ->icon('heroicon-o-paper-airplane')
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription(fn (Invoice $record) => "This emails {$record->client_email} with the PDF attached, and locks the invoice so its figures can no longer change.")
            ->visible(fn (Invoice $record) => filled($record->client_email)
                && $record->status !== InvoiceStatus::Cancelled)
            ->action(function (Invoice $record, SendInvoice $send): void {
                try {
                    $send->handle($record);
                } catch (\Throwable $e) {
                    Notification::make()->danger()
                        ->title('Could not send')
                        ->body($e->getMessage())
                        ->send();

                    return;
                }

                Notification::make()->success()
                    ->title('Invoice sent')
                    ->body("Emailed to {$record->client_email}.")
                    ->send();
            });
    }

    /** A signed, expiring link the studio can paste into WhatsApp. */
    public static function copyLinkAction(): Action
    {
        return Action::make('copyLink')
            ->label('Copy client link')
            ->icon('heroicon-o-link')
            ->action(function (Invoice $record): void {
                Notification::make()
                    ->title('Client download link (valid 30 days)')
                    ->body($record->downloadUrl())
                    ->persistent()
                    ->send();
            });
    }
}
