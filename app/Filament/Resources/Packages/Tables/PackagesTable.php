<?php

declare(strict_types=1);

namespace App\Filament\Resources\Packages\Tables;

use App\Enums\PackageCategory;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PackagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('name')->searchable()->sortable()->weight('medium'),

                TextColumn::make('category')
                    ->formatStateUsing(fn (?PackageCategory $state) => $state?->label() ?? '—')
                    ->badge()
                    ->sortable(),

                TextColumn::make('price_cents')
                    ->label('Price')
                    ->sortable()
                    // Formatted only at display time; the column is an integer.
                    ->formatStateUsing(fn ($state, $record) => $record->displayPrice()),

                TextColumn::make('duration_hours')->label('Hours')->placeholder('—'),

                TextColumn::make('bookings_count')
                    ->label('Bookings')
                    ->counts('bookings')
                    ->sortable(),

                IconColumn::make('is_popular')->label('Popular')->boolean(),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Active'),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])])
            ->emptyStateHeading('No packages yet')
            ->emptyStateDescription('Add your packages so couples can see prices without asking.');
    }
}
