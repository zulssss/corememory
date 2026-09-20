<?php

declare(strict_types=1);

namespace App\Filament\Resources\Bookings\Schemas;

use App\Enums\BookingStatus;
use App\Enums\EnquirySource;
use App\Enums\SessionSlot;
use App\Filament\Forms\Components\MoneyInput;
use App\Models\Package;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BookingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Enquiry')
                    ->columns(3)
                    ->schema([
                        TextInput::make('reference')
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Allocated automatically and never reused.'),

                        // Status is deliberately NOT editable here. Changing it
                        // by assignment would update the badge without touching
                        // slot_holds — the date would stay held after a
                        // cancellation, or not get held on confirmation. The
                        // status actions on the list and edit pages route
                        // through ChangeBookingStatus instead.
                        TextInput::make('status')
                            ->disabled()
                            ->dehydrated(false)
                            // Filament hands this the raw column value, not the
                            // cast enum, so resolve it rather than assuming.
                            ->formatStateUsing(fn ($state) => $state instanceof BookingStatus
                                ? $state->label()
                                : BookingStatus::tryFrom((string) $state)?->label())
                            ->helperText('Change this with the status buttons, so the calendar stays in step.'),

                        Select::make('source')
                            ->options(EnquirySource::class)
                            ->native(false),
                    ]),

                Section::make('The couple')
                    ->columns(2)
                    ->schema([
                        TextInput::make('partner_one_name')->label('Partner one')->required(),
                        TextInput::make('partner_two_name')->label('Partner two'),
                        TextInput::make('email')->email()->required(),
                        TextInput::make('phone')->tel()->required(),
                        TextInput::make('guest_count')->label('Guests')->numeric(),
                    ]),

                Section::make('Dates')
                    ->description('A nikah and a reception belong to one booking. Editing dates here does NOT move the calendar hold — cancel and re-confirm to do that.')
                    ->schema([
                        Repeater::make('dates')
                            ->relationship()
                            ->hiddenLabel()
                            ->columns(3)
                            ->schema([
                                DatePicker::make('event_date')
                                    ->required()
                                    ->native(false)
                                    ->displayFormat('d M Y'),

                                Select::make('session_slot')
                                    ->options(SessionSlot::class)
                                    ->required()
                                    ->native(false),

                                TextInput::make('label')->placeholder('Nikah / Reception'),
                                TextInput::make('venue'),
                                TextInput::make('city'),
                                TextInput::make('state'),
                            ])
                            ->addActionLabel('Add a date')
                            ->defaultItems(1),
                    ]),

                Section::make('Quote')
                    ->description('Prices were captured when the couple booked. They do not change if you later edit a package.')
                    ->columns(2)
                    ->schema([
                        Select::make('package_id')
                            ->label('Package')
                            ->options(fn () => Package::orderBy('sort_order')->pluck('name', 'id'))
                            ->native(false),

                        MoneyInput::make('estimated_total_cents', 'Estimated total'),

                        MoneyInput::make('deposit_cents', 'Deposit'),
                    ]),

                Section::make('Notes')
                    ->columns(2)
                    ->schema([
                        Textarea::make('notes')
                            ->label('From the couple')
                            ->rows(4)
                            ->disabled()
                            ->dehydrated(false),

                        Textarea::make('admin_notes')
                            ->label('Internal notes')
                            ->rows(4)
                            ->helperText('Only the studio sees this.'),
                    ]),
            ])
            ->columns(1);
    }
}
