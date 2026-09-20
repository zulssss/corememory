<?php

declare(strict_types=1);

namespace App\Filament\Resources\BlockedDates\Tables;

use App\Enums\SessionSlot;
use App\Exceptions\SlotUnavailableException;
use App\Models\BlockedDate;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BlockedDatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('date')
            ->columns([
                TextColumn::make('date')
                    ->date('d M Y')
                    ->sortable()
                    ->weight('medium')
                    ->description(fn (BlockedDate $record) => $record->date->translatedFormat('l')),

                TextColumn::make('session_slot')
                    ->label('Session')
                    ->formatStateUsing(fn (?SessionSlot $state) => $state?->labelWithTime() ?? 'Whole day')
                    ->badge()
                    ->color(fn (?SessionSlot $state) => $state === null ? 'danger' : 'warning'),

                TextColumn::make('reason')->placeholder('—')->wrap(),
            ])
            ->headerActions([self::blockRangeAction()])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])])
            ->emptyStateHeading('Nothing blocked')
            ->emptyStateDescription('Every date is open unless a booking or a block takes it.');
    }

    /**
     * Bulk-block a date range — a holiday, or time off.
     *
     * Dates already held by a confirmed booking are SKIPPED rather than
     * failing the whole range: blocking a fortnight should not be refused
     * because one Saturday in it is already a wedding. The studio is told
     * exactly what was skipped.
     */
    public static function blockRangeAction(): Action
    {
        return Action::make('blockRange')
            ->label('Block a date range')
            ->icon('heroicon-o-calendar-days')
            ->schema([
                DatePicker::make('from')->required()->native(false)->displayFormat('d M Y'),
                DatePicker::make('until')->required()->native(false)->displayFormat('d M Y')
                    ->after('from'),

                Select::make('session_slot')
                    ->label('Session')
                    ->options(fn () => collect(SessionSlot::concrete())
                        ->mapWithKeys(fn (SessionSlot $s) => [$s->value => $s->labelWithTime()])
                        ->all())
                    ->placeholder('The whole day')
                    ->native(false),

                TextInput::make('reason')->maxLength(160)->placeholder('Studio leave'),
            ])
            ->action(function (array $data): void {
                $period = CarbonPeriod::create(
                    CarbonImmutable::parse($data['from'])->startOfDay(),
                    CarbonImmutable::parse($data['until'])->startOfDay(),
                );

                $blocked = 0;
                $skipped = [];

                foreach ($period as $day) {
                    try {
                        BlockedDate::create([
                            'date' => $day,
                            'session_slot' => $data['session_slot'] ?? null,
                            'reason' => $data['reason'] ?? null,
                        ]);
                        $blocked++;
                    } catch (SlotUnavailableException) {
                        $skipped[] = $day->translatedFormat('d M');
                    }
                }

                $notification = Notification::make()->success()
                    ->title("Blocked {$blocked} date(s)");

                if ($skipped !== []) {
                    $notification
                        ->warning()
                        ->title("Blocked {$blocked} date(s), skipped ".count($skipped))
                        ->body('Already booked: '.implode(', ', $skipped))
                        ->persistent();
                }

                $notification->send();
            });
    }
}
