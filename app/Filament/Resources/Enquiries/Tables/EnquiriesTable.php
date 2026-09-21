<?php

declare(strict_types=1);

namespace App\Filament\Resources\Enquiries\Tables;

use App\Enums\EnquiryStatus;
use App\Models\Enquiry;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EnquiriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->weight('medium')
                    ->description(fn (Enquiry $record) => $record->email),

                TextColumn::make('message')->limit(80)->wrap()->searchable(),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (EnquiryStatus $state) => $state->label())
                    ->color(fn (EnquiryStatus $state) => $state->color())
                    ->sortable(),

                TextColumn::make('created_at')->label('Received')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(EnquiryStatus::options())->multiple(),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),

                    Action::make('whatsapp')
                        ->label('WhatsApp')
                        ->icon('heroicon-o-chat-bubble-left-right')
                        ->color('success')
                        ->url(fn (Enquiry $record) => $record->whatsappUrl())
                        ->openUrlInNewTab()
                        ->visible(fn (Enquiry $record) => $record->whatsappUrl() !== null),

                    Action::make('email')
                        ->label('Reply by email')
                        ->icon('heroicon-o-envelope')
                        ->url(fn (Enquiry $record) => 'mailto:'.$record->email)
                        ->openUrlInNewTab(),

                    Action::make('markReplied')
                        ->label('Mark as replied')
                        ->icon('heroicon-o-check')
                        ->visible(fn (Enquiry $record) => $record->status === EnquiryStatus::New)
                        ->action(function (Enquiry $record): void {
                            $record->update([
                                'status' => EnquiryStatus::Replied,
                                'replied_at' => now(),
                            ]);

                            Notification::make()->success()->title('Marked as replied')->send();
                        }),
                ]),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])])
            ->emptyStateHeading('No messages yet');
    }
}
