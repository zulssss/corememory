<?php

declare(strict_types=1);

namespace App\Filament\Resources\Invoices\RelationManagers;

use App\Actions\Invoices\RecordPayment;
use App\Enums\PaymentMethod;
use App\Filament\Forms\Components\MoneyInput;
use App\Models\Invoice;
use App\Models\Payment;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Payments received against an invoice.
 *
 * Creating and deleting both route through RecordPayment, because settling an
 * invoice is not just inserting a row — the invoice has to flip to Paid, and
 * back to Sent if a payment is removed. Doing it directly would leave a paid
 * invoice showing as overdue forever and the receivables figure permanently
 * wrong.
 */
class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Payments received';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            MoneyInput::make('amount_cents', 'Amount')->required(),

            DatePicker::make('paid_at')
                ->label('Date received')
                ->required()
                ->native(false)
                ->displayFormat('d M Y')
                ->default(today()),

            Select::make('method')
                ->options(PaymentMethod::class)
                ->required()
                ->native(false)
                ->default(PaymentMethod::BankTransfer->value),

            TextInput::make('reference')
                ->label('Reference')
                ->helperText('The transfer or transaction reference.'),

            SpatieMediaLibraryFileUpload::make('receipt')
                ->collection('receipt')
                // Deliberately the PRIVATE disk: a payment receipt carries a
                // client's banking details and must never be web-servable.
                ->disk(config('filesystems.private_disk'))
                ->label('Receipt')
                ->image()
                ->helperText('Optional photo of the transfer slip.'),

            Textarea::make('notes')->rows(2),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('reference')
            ->columns([
                TextColumn::make('paid_at')->label('Received')->date('d M Y')->sortable(),

                TextColumn::make('amount_cents')
                    ->label('Amount')
                    ->formatStateUsing(fn (Payment $record) => $record->amount_cents->format())
                    ->alignEnd(),

                TextColumn::make('method')
                    ->badge()
                    ->formatStateUsing(fn (PaymentMethod $state) => $state->label()),

                TextColumn::make('reference')->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Record a payment')
                    ->using(function (array $data, RelationManager $livewire): Payment {
                        /** @var Invoice $invoice */
                        $invoice = $livewire->getOwnerRecord();

                        return app(RecordPayment::class)->handle($invoice, $data);
                    }),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->using(fn (Payment $record) => app(RecordPayment::class)->remove($record)),
            ])
            ->emptyStateHeading('Nothing received yet');
    }
}
