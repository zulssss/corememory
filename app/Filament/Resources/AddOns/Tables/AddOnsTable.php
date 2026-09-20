<?php

declare(strict_types=1);

namespace App\Filament\Resources\AddOns\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class AddOnsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('name')->searchable()->sortable()->weight('medium'),

                TextColumn::make('price_cents')
                    ->label('Price')
                    ->sortable()
                    ->formatStateUsing(fn ($record) => $record->price_cents->formatCompact()),

                IconColumn::make('is_quantifiable')->label('Qty')->boolean(),

                TextColumn::make('max_qty')
                    ->label('Max')
                    ->formatStateUsing(fn ($state, $record) => $record->is_quantifiable ? $state : '—'),

                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->filters([TernaryFilter::make('is_active')->label('Active')])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])])
            ->emptyStateHeading('No add-ons yet');
    }
}
