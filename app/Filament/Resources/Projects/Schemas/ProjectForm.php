<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\Schemas;

use App\Enums\ProjectCategory;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('The story')
                    ->description('What this wedding was, and who it was for.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Usually the couple\'s names. The web address is generated from this and does not change when you edit it later.')
                            ->columnSpanFull(),

                        Select::make('category')
                            ->options(ProjectCategory::class)
                            ->required()
                            ->native(false)
                            ->helperText('Controls which filter this appears under on the Work page.'),

                        TextInput::make('couple_names')
                            ->label('Couple')
                            ->maxLength(255),

                        DatePicker::make('event_date')
                            ->label('Event date')
                            ->displayFormat('d M Y')
                            ->native(false),

                        TextInput::make('venue')->maxLength(255),
                        TextInput::make('city')->maxLength(255),
                        TextInput::make('state')->maxLength(255),

                        Textarea::make('excerpt')
                            ->rows(2)
                            ->maxLength(500)
                            ->helperText('One or two lines, shown under the image in listings.')
                            ->columnSpanFull(),

                        Textarea::make('description')
                            ->rows(8)
                            ->helperText('The long-form story shown on the project page.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Images')
                    ->description('The hero is the large image at the top. The gallery is the story below it — drag to reorder.')
                    ->schema([
                        // Conversions are queued, so a large upload returns
                        // immediately and the WebP versions appear shortly after.
                        // This needs a queue worker running in production.
                        SpatieMediaLibraryFileUpload::make('hero')
                            ->collection('hero')
                            // Filament defaults uploads to FILESYSTEM_DISK, which is
                            // the PRIVATE 'local' disk. Media landing there is not
                            // web-servable, so the public site gets a 403 and shows a
                            // broken image over its blur placeholder. Anything a
                            // visitor must see goes to the media library's public
                            // disk — `public` locally, a public bucket on Cloud.
                            ->disk(config('media-library.disk_name'))
                            ->image()
                            ->imageEditor()
                            ->maxSize(12 * 1024)
                            ->helperText('One image. Uploading a new one replaces the old.'),

                        SpatieMediaLibraryFileUpload::make('gallery')
                            ->collection('gallery')
                            ->disk(config('media-library.disk_name'))   // see the hero field above
                            ->image()
                            ->multiple()
                            ->reorderable()
                            ->appendFiles()
                            ->maxSize(12 * 1024)
                            ->panelLayout('grid')
                            ->helperText('Drag to set the order they appear in the story.'),
                    ]),

                Section::make('Crew credits')
                    ->schema([
                        Repeater::make('crew')
                            ->hiddenLabel()
                            ->schema([
                                TextInput::make('role')
                                    ->placeholder('Photographer')
                                    ->required(),
                                TextInput::make('name')
                                    ->placeholder('Zul')
                                    ->required(),
                            ])
                            ->columns(2)
                            ->addActionLabel('Add crew member')
                            ->reorderable()
                            ->defaultItems(0),
                    ]),

                Section::make('Publishing')
                    ->columns(3)
                    ->schema([
                        DateTimePicker::make('published_at')
                            ->label('Published')
                            ->native(false)
                            ->displayFormat('d M Y H:i')
                            ->helperText('Leave empty to keep this as a draft, hidden from the public site.'),

                        Toggle::make('is_featured')
                            ->label('Featured')
                            ->helperText('Featured stories appear on the homepage.'),

                        TextInput::make('sort_order')
                            ->numeric()
                            ->default(0)
                            ->helperText('Lower numbers appear first.'),
                    ]),
            ])
            ->columns(1);
    }
}
