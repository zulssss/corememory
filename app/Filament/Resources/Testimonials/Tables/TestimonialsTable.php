<?php

declare(strict_types=1);

namespace App\Filament\Resources\Testimonials\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class TestimonialsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Eager-load the linked project so the column below is not an N+1.
            ->modifyQueryUsing(fn ($query) => $query->with('project'))
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('couple_name')
                    ->label('Couple')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('quote')
                    ->limit(70)
                    ->wrap()
                    ->searchable(),

                TextColumn::make('context')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('project.title')
                    ->label('Story')
                    ->placeholder('Not linked')
                    ->toggleable(),

                IconColumn::make('is_published')
                    ->label('Published')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_published')->label('Published'),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ])
            ->emptyStateHeading('No testimonials yet');
    }
}
