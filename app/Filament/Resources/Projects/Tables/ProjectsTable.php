<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\Tables;

use App\Enums\ProjectCategory;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ProjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Eager-load media so the thumbnail column doesn't fire a query per
            // row. Without this the list page is a textbook N+1.
            ->modifyQueryUsing(fn ($query) => $query->with('media'))
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                SpatieMediaLibraryImageColumn::make('hero')
                    ->label('')
                    ->collection('hero')
                    ->conversion('thumb')
                    ->height(44),

                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->description(fn ($record) => $record->venue),

                TextColumn::make('category')
                    ->badge()
                    ->sortable(),

                TextColumn::make('event_date')
                    ->label('Event')
                    ->date('d M Y')
                    ->sortable()
                    ->placeholder('—'),

                IconColumn::make('is_featured')
                    ->label('Featured')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('published_at')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state === null ? 'Draft' : 'Published')
                    ->color(fn ($state) => $state === null ? 'gray' : 'success')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->options(ProjectCategory::class)
                    ->multiple(),

                TernaryFilter::make('is_featured')
                    ->label('Featured'),

                TernaryFilter::make('published_at')
                    ->label('Published')
                    ->nullable()
                    ->placeholder('All')
                    ->trueLabel('Published only')
                    ->falseLabel('Drafts only')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('published_at'),
                        false: fn ($query) => $query->whereNull('published_at'),
                        blank: fn ($query) => $query,
                    ),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No wedding stories yet')
            ->emptyStateDescription('Add your first project to start filling the portfolio.');
    }
}
