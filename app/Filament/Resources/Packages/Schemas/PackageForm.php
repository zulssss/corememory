<?php

declare(strict_types=1);

namespace App\Filament\Resources\Packages\Schemas;

use App\Enums\PackageCategory;
use App\Filament\Forms\Components\MoneyInput;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PackageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('The package')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')->required()->maxLength(120)->columnSpanFull(),

                        // Required: the packages page and the booking wizard both
                        // group by category, so a package without one would be
                        // published at a price and still never be shown.
                        Select::make('category')
                            ->options(collect(PackageCategory::ordered())
                                ->mapWithKeys(fn (PackageCategory $c) => [$c->value => $c->label()]))
                            ->required()
                            ->native(false)
                            ->helperText('Which tab this package appears under on the website.')
                            ->columnSpanFull(),

                        MoneyInput::make('price_cents', 'Price')->required(),

                        Toggle::make('price_is_from')
                            ->label('Show as a starting price')
                            ->helperText('Displays "From RM 3,800" rather than a fixed price.'),

                        TextInput::make('duration_hours')
                            ->label('Hours of coverage')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(48),

                        Textarea::make('description')
                            ->rows(3)
                            ->maxLength(600)
                            ->columnSpanFull(),
                    ]),

                Section::make("What's included")
                    ->description('Be explicit. Hours of coverage, number of edited photos, crew on site, delivery time, raw files, travel radius, album. A couple should never have to ask.')
                    ->schema([
                        Repeater::make('inclusions')
                            ->hiddenLabel()
                            ->simple(
                                TextInput::make('inclusion')
                                    ->required()
                                    ->placeholder('8 hours of coverage')
                                    ->maxLength(160),
                            )
                            ->addActionLabel('Add an inclusion')
                            ->reorderable()
                            ->defaultItems(1),
                    ]),

                Section::make('Display')
                    ->columns(3)
                    ->schema([
                        Toggle::make('is_popular')
                            ->label('Most popular')
                            ->helperText('Highlights this tier on the pricing page.'),

                        Toggle::make('is_active')
                            ->label('Available to book')
                            ->default(true)
                            ->helperText('Turning this off hides the package from the website. Existing bookings keep their prices.'),

                        TextInput::make('sort_order')->numeric()->default(0),
                    ]),
            ])
            ->columns(1);
    }
}
