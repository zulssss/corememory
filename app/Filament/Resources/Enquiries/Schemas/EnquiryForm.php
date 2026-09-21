<?php

declare(strict_types=1);

namespace App\Filament\Resources\Enquiries\Schemas;

use App\Enums\EnquiryStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EnquiryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Message')
                    ->columns(3)
                    ->schema([
                        TextInput::make('name')->required(),
                        TextInput::make('email')->email()->required(),
                        TextInput::make('phone')->tel(),

                        // Read-only: this is what the visitor wrote. Editing
                        // someone else's words is never the right move.
                        Textarea::make('message')
                            ->rows(6)
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpanFull(),
                    ]),

                Section::make('Handling')
                    ->columns(2)
                    ->schema([
                        Select::make('status')
                            ->options(EnquiryStatus::class)
                            ->required()
                            ->native(false)
                            ->default(EnquiryStatus::New->value),

                        DateTimePicker::make('replied_at')
                            ->label('Replied')
                            ->native(false)
                            ->displayFormat('d M Y H:i'),

                        Textarea::make('admin_notes')
                            ->label('Internal notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ])
            ->columns(1);
    }
}
