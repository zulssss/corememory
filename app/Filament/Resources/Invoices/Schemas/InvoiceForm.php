<?php

declare(strict_types=1);

namespace App\Filament\Resources\Invoices\Schemas;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Filament\Forms\Components\MoneyInput;
use App\Models\Invoice;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Line items are editable ONLY while the invoice is a draft.
 *
 * Once it has been sent, the client is holding a PDF with these numbers on it.
 * Letting them drift afterwards means the studio and the client are looking at
 * two different documents that share a number — so the whole items section
 * goes read-only on lock.
 */
class InvoiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Invoice')
                    ->columns(3)
                    ->schema([
                        TextInput::make('number')
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Sequential and never reused.'),

                        Select::make('type')
                            ->options(InvoiceType::class)
                            ->required()
                            ->native(false)
                            ->disabled(fn (?Invoice $record) => $record && ! $record->isEditable()),

                        TextInput::make('status')
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(fn ($state) => $state instanceof InvoiceStatus
                                ? $state->label()
                                : InvoiceStatus::tryFrom((string) $state)?->label())
                            ->helperText('Use the Send and Record payment buttons to change this.'),

                        DatePicker::make('issued_at')->label('Issued')->native(false)->displayFormat('d M Y'),
                        DatePicker::make('due_at')->label('Due')->native(false)->displayFormat('d M Y'),
                    ]),

                Section::make('Bill to')
                    ->description('Copied from the booking when the invoice was created. Editing here does not change the booking — and a later change to the booking will not change this invoice.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('client_name')->required(),
                        TextInput::make('client_email')->email(),
                        TextInput::make('client_phone')->tel(),
                        Textarea::make('client_address')->rows(3),
                    ]),

                Section::make('Line items')
                    ->description(fn (?Invoice $record) => $record && ! $record->isEditable()
                        ? 'This invoice has been sent, so its line items are locked.'
                        : 'Negative amounts are deductions — a deposit already invoiced, or a balance deferred.')
                    ->schema([
                        Repeater::make('items')
                            ->relationship()
                            ->hiddenLabel()
                            ->columns(4)
                            ->schema([
                                TextInput::make('description')
                                    ->required()
                                    ->columnSpan(2),

                                TextInput::make('qty')
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(1),

                                MoneyInput::make('unit_price_cents', 'Unit price')
                                    ->required()
                                    ->helperText('Negative for a deduction.'),
                            ])
                            ->addActionLabel('Add a line')
                            ->reorderable()
                            ->disabled(fn (?Invoice $record) => $record && ! $record->isEditable())
                            // Keep total_cents consistent with qty x unit price
                            // so the stored figure and the printed line always
                            // agree.
                            ->mutateRelationshipDataBeforeCreateUsing(fn (array $data) => [
                                ...$data,
                                'total_cents' => (int) $data['unit_price_cents'] * (int) ($data['qty'] ?? 1),
                            ])
                            ->mutateRelationshipDataBeforeSaveUsing(fn (array $data) => [
                                ...$data,
                                'total_cents' => (int) $data['unit_price_cents'] * (int) ($data['qty'] ?? 1),
                            ]),
                    ]),

                Section::make('Notes')
                    ->schema([
                        Textarea::make('notes')
                            ->rows(3)
                            ->helperText('Printed at the bottom of the invoice.'),
                    ]),
            ])
            ->columns(1);
    }
}
