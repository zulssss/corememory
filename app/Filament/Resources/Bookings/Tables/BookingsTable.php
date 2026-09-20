<?php

declare(strict_types=1);

namespace App\Filament\Resources\Bookings\Tables;

use App\Actions\Bookings\ChangeBookingStatus;
use App\Enums\BookingStatus;
use App\Enums\EnquirySource;
use App\Exceptions\SlotUnavailableException;
use App\Filament\Actions\ExportBookingsCsv;
use App\Models\Booking;
use App\Models\Package;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The bookings list — the studio's daily driver.
 *
 * The status action is the important part: it routes through
 * ChangeBookingStatus so confirming writes calendar holds and cancelling
 * releases them. A plain status dropdown would change the badge and silently
 * leave the calendar wrong, which is the single most expensive bug this
 * system could have.
 */
class BookingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Eager-load everything the columns touch, or each row fires its
            // own queries for the package and the dates.
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['package', 'dates']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('reference')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->copyable()
                    ->copyMessage('Reference copied'),

                TextColumn::make('partner_one_name')
                    ->label('Couple')
                    ->searchable(['partner_one_name', 'partner_two_name', 'email'])
                    ->formatStateUsing(fn (Booking $record) => $record->coupleNames())
                    ->description(fn (Booking $record) => $record->email),

                TextColumn::make('dates.event_date')
                    ->label('Event')
                    ->formatStateUsing(fn ($state) => $state?->translatedFormat('d M Y'))
                    ->listWithLineBreaks()
                    ->limitList(2)
                    ->sortable(),

                TextColumn::make('package.name')
                    ->label('Package')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('estimated_total_cents')
                    ->label('Total')
                    ->formatStateUsing(fn (Booking $record) => $record->estimated_total_cents->formatCompact())
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (BookingStatus $state) => $state->label())
                    ->color(fn (BookingStatus $state) => $state->color())
                    ->sortable(),

                TextColumn::make('source')
                    ->formatStateUsing(fn (?EnquirySource $state) => $state?->label() ?? '—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Enquired')
                    ->since()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(BookingStatus::options())
                    ->multiple(),

                SelectFilter::make('package_id')
                    ->label('Package')
                    ->options(fn () => Package::orderBy('sort_order')->pluck('name', 'id'))
                    ->multiple(),

                SelectFilter::make('source')
                    ->options(EnquirySource::options())
                    ->multiple(),

                Filter::make('event_date')
                    ->schema([
                        DatePicker::make('from')->label('Event from')->native(false),
                        DatePicker::make('until')->label('Event until')->native(false),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereHas('dates',
                            fn (Builder $d) => $d->whereDate('event_date', '>=', $date)))
                        ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereHas('dates',
                            fn (Builder $d) => $d->whereDate('event_date', '<=', $date)))),

                Filter::make('upcoming')
                    ->label('Upcoming events only')
                    ->query(fn (Builder $query) => $query->upcoming())
                    ->toggle(),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),

                    self::statusAction(),
                    self::whatsappAction(),

                    Action::make('email')
                        ->label('Email couple')
                        ->icon('heroicon-o-envelope')
                        ->url(fn (Booking $record) => 'mailto:'.$record->email
                            .'?subject='.rawurlencode('Your booking with CoreMemory ('.$record->reference.')'))
                        ->openUrlInNewTab(),
                ]),
            ])
            ->headerActions([
                ExportBookingsCsv::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ])
            ->emptyStateHeading('No bookings yet')
            ->emptyStateDescription('Enquiries from the website land here.');
    }

    /**
     * Change status through the Action layer.
     *
     * If the new status holds the slot and something else already has it, the
     * studio is told plainly rather than the change half-applying.
     */
    public static function statusAction(): Action
    {
        return Action::make('changeStatus')
            ->label('Change status')
            ->icon('heroicon-o-arrow-path')
            ->schema([
                Select::make('status')
                    ->label('New status')
                    ->options(BookingStatus::options())
                    ->required()
                    ->native(false)
                    ->helperText('Confirmed and Deposit paid hold the date on the calendar. Everything else releases it.'),

                Textarea::make('note')
                    ->label('Add a note')
                    ->rows(2),
            ])
            ->action(function (Booking $record, array $data): void {
                $status = BookingStatus::from($data['status']);

                try {
                    app(ChangeBookingStatus::class)->handle($record, $status, auth()->user());
                } catch (SlotUnavailableException $e) {
                    Notification::make()
                        ->danger()
                        ->title('That date is no longer free')
                        ->body($e->getMessage())
                        ->persistent()
                        ->send();

                    return;
                }

                if (filled($data['note'] ?? null)) {
                    $record->notes()->create([
                        'user_id' => auth()->id(),
                        'body' => $data['note'],
                        'is_system' => false,
                    ]);
                }

                Notification::make()
                    ->success()
                    ->title('Status updated')
                    ->body('This booking is now '.$status->label().'.')
                    ->send();
            });
    }

    /** One-click WhatsApp reply, pre-filled with the couple's name and reference. */
    public static function whatsappAction(): Action
    {
        return Action::make('whatsapp')
            ->label('WhatsApp')
            ->icon('heroicon-o-chat-bubble-left-right')
            ->color('success')
            ->url(fn (Booking $record) => $record->whatsappUrl())
            ->openUrlInNewTab()
            ->visible(fn (Booking $record) => $record->whatsappUrl() !== null);
    }
}
