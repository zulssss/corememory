<?php

declare(strict_types=1);

namespace App\Filament\Resources\Testimonials\Schemas;

use App\Models\Project;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TestimonialForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        TextInput::make('couple_name')
                            ->label('Couple')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('context')
                            ->label('Context')
                            ->placeholder('Wedding, 2026')
                            ->maxLength(255)
                            ->helperText('The small line shown above the quote.'),

                        Textarea::make('quote')
                            ->required()
                            ->rows(4)
                            ->maxLength(1000)
                            ->columnSpanFull(),

                        Select::make('project_id')
                            ->label('Linked wedding story')
                            ->options(fn () => Project::orderBy('title')->pluck('title', 'id'))
                            ->searchable()
                            ->preload()
                            ->placeholder('Not linked')
                            ->helperText('Optional. Links this quote to a story in the portfolio.'),

                        TextInput::make('sort_order')
                            ->numeric()
                            ->default(0)
                            ->helperText('Every published testimonial appears on the homepage. Lower numbers appear first — or drag to reorder in the list.'),

                        Toggle::make('is_published')
                            ->label('Published')
                            ->default(true)
                            ->helperText('Unpublished quotes are hidden from the website.'),
                    ]),
            ])
            ->columns(1);
    }
}
