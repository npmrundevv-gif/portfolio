<?php

namespace App\Filament\Resources\CaseStudies\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;

class CaseStudyForm
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
                TextInput::make('title')
                    ->required(),
                Textarea::make('caption')
                    ->default(null)
                    ->columnSpanFull(),
                TagsInput::make('tags')
                    ->default(null)
                    ->columnSpanFull(),
                FileUpload::make('image_path')
                    ->image()
                    ->disk('public')
                    ->directory('case-studies')
                    ->visibility('public')
                    ->maxSize(10240)
                    ->imageEditor(),
                TextInput::make('url')
                    ->label('Case Study URL')
                    ->placeholder('https://... or #anchor')
                    ->default(null)
                    ->rules([
                        'nullable',
                        'string',
                        'max:500',
                        function ($attribute, $value, $fail) {
                            if (!empty($value) && !\App\Models\SiteSetting::isSafeUrl($value, true)) {
                                $fail('The URL must be a valid http(s) URL or anchor (#).');
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
