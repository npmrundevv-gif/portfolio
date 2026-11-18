<?php

namespace App\Filament\Resources\TimelineEvents\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TagsInput;

class TimelineEventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('section_id')
                    ->relationship('section', 'title')
                    ->getOptionLabelFromRecordUsing(fn($record) => $record->title ?? $record->key)
                    ->required()
                    ->searchable()
                    ->preload()
                    ->hidden(fn($livewire) => $livewire instanceof \Filament\Resources\RelationManagers\RelationManager),
                TextInput::make('year')
                    ->required()
                    ->placeholder('e.g. 2020 or 2020–2021'),
                TextInput::make('title')
                    ->required(),
                Textarea::make('description')
                    ->default(null)
                    ->columnSpanFull(),
                TagsInput::make('tags')
                    ->placeholder('Add a tag')
                    ->default(null),
                FileUpload::make('image_path')
                    ->image()
                    ->disk('public')
                    ->directory('timeline-events')
                    ->visibility('public')
                    ->maxSize(10240)
                    ->imageEditor(),
                TextInput::make('url')
                    ->url()
                    ->default(null)
                    ->placeholder('https://...')
                    ->rules([
                        'nullable',
                        'string',
                        'max:500',
                        function ($attribute, $value, $fail) {
                            if (!empty($value) && !\App\Models\SiteSetting::isSafeUrl($value)) {
                                $fail('The URL must be a valid http(s) URL.');
                            }
                        },
                    ]),
                TextInput::make('sort_order')
                    ->required()
                    ->numeric()
                    ->default(0),
                Toggle::make('enabled')
                    ->default(true)
                    ->inline(false),
            ]);
    }
}
