<?php

declare(strict_types=1);

namespace App\Filament\Resources\AddOns\Schemas;

use App\Filament\Forms\Components\MoneyInput;
use App\Models\Package;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AddOnForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('The add-on')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')->required()->maxLength(120)->columnSpanFull(),

                        MoneyInput::make('price_cents', 'Price')->required(),

                        Toggle::make('is_active')->label('Available to book')->default(true),

                        Textarea::make('description')->rows(2)->maxLength(400)->columnSpanFull(),
                    ]),

                Section::make('Quantity')
                    ->description('Some add-ons are naturally bought more than once — an extra hour, or a travel day. Those show a quantity picker instead of a simple toggle.')
                    ->columns(2)
                    ->schema([
                        Toggle::make('is_quantifiable')
                            ->label('Couples can choose a quantity')
                            ->live(),

                        TextInput::make('max_qty')
                            ->label('Maximum quantity')
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->maxValue(99)
                            ->visible(fn ($get) => (bool) $get('is_quantifiable'))
                            ->helperText('Stops the booking form offering something absurd, like 40 extra hours.'),
                    ]),

                Section::make('Availability')
                    ->schema([
                        Select::make('applies_to')
                            ->label('Offered with')
                            ->multiple()
                            ->options(fn () => Package::orderBy('sort_order')->pluck('name', 'id'))
                            ->placeholder('Every package')
                            ->helperText('Leave empty to offer this with every package.'),
                    ]),

                Section::make('Display')
                    ->schema([
                        TextInput::make('sort_order')->numeric()->default(0),
                    ]),
            ])
            ->columns(1);
    }
}
