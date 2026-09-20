<?php

declare(strict_types=1);

namespace App\Filament\Resources\BlockedDates\Schemas;

use App\Enums\SessionSlot;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BlockedDateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Block a date')
                    ->description('Blocked dates disappear from the public calendar immediately. Use this for leave, public holidays, or a wedding booked offline.')
                    ->columns(2)
                    ->schema([
                        DatePicker::make('date')
                            ->required()
                            ->native(false)
                            ->displayFormat('d M Y'),

                        Select::make('session_slot')
                            ->label('Session')
                            ->options(fn () => collect(SessionSlot::concrete())
                                ->mapWithKeys(fn (SessionSlot $s) => [$s->value => $s->labelWithTime()])
                                ->all())
                            ->placeholder('The whole day')
                            ->native(false)
                            ->helperText('Leave empty to block the entire day.'),

                        TextInput::make('reason')
                            ->maxLength(160)
                            ->placeholder('Studio leave')
                            ->columnSpanFull()
                            ->helperText('Only the studio sees this. It appears on the calendar as the reason a slot is unavailable.'),
                    ]),
            ])
            ->columns(1);
    }
}
