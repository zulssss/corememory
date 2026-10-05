<?php

declare(strict_types=1);

namespace App\Filament\Resources\Posts\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->helperText('The web address is generated from this and does not change when you edit it later.'),

                        Textarea::make('excerpt')
                            ->rows(2)
                            ->maxLength(500)
                            ->helperText('Shown in listings and used as the search description.'),

                        Textarea::make('body')
                            ->rows(16),

                        SpatieMediaLibraryFileUpload::make('cover')
                            ->collection('cover')
                            ->disk(config('media-library.disk_name'))   // public site asset — never the private default disk
                            ->image()
                            ->imageEditor()
                            ->maxSize(12 * 1024),

                        DateTimePicker::make('published_at')
                            ->label('Published')
                            ->native(false)
                            ->displayFormat('d M Y H:i')
                            ->helperText('Leave empty to keep this as a draft.'),
                    ]),
            ])
            ->columns(1);
    }
}
