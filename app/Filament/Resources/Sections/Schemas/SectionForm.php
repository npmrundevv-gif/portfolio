<?php

namespace App\Filament\Resources\Sections\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

use Filament\Forms\Components\KeyValue;
use Filament\Schemas\Components\Section as FormSection;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Select;

class SectionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                FormSection::make('Basic Configuration')
                    ->schema([
                        TextInput::make('key')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->rules([
                                'alpha_dash',
                                'lowercase',
                                \Illuminate\Validation\Rule::in(array_keys(\App\Models\Section::TEMPLATES)),
                            ])
                            ->validationMessages([
                                'in' => 'The section key must be one of the supported template keys: ' . implode(', ', array_keys(\App\Models\Section::TEMPLATES)) . '.',
                            ])
                            ->helperText('Supported keys: ' . implode(', ', array_keys(\App\Models\Section::TEMPLATES)))
                            ->disabledOn('edit'),
                        TextInput::make('title')
                            ->required(),
                        TextInput::make('subtitle')
                            ->default(null),
                        Toggle::make('enabled')
                            ->default(true),
                        TextInput::make('sort_order')
                            ->required()
                            ->numeric()
                            ->default(0),
                    ]),

                FormSection::make('Hero Settings')
                    ->hidden(fn($record) => $record?->key !== 'hero')
                    ->statePath('data')
                    ->schema([
                        TextInput::make('video_url')
                            ->label('Hero Video URL')
                            ->url()
                            ->rules([
                                'nullable',
                                'string',
                                'max:500',
                                function ($attribute, $value, $fail) {
                                    if (!empty($value) && !\App\Models\SiteSetting::isSafeUrl($value)) {
                                        $fail('The Hero Video URL must be a valid http(s) URL.');
                                    }
                                },
                            ]),
                    ]),

                FormSection::make('Marquee Settings')
                    ->hidden(fn($record) => $record?->key !== 'marquee')
                    ->statePath('data')
                    ->schema([
                        TextInput::make('text')
                            ->label('Marquee Text')
                            ->required(),
                    ]),

                FormSection::make('Stack3D Settings')
                    ->hidden(fn($record) => $record?->key !== 'stack3d')
                    ->statePath('data')
                    ->schema([
                        TextInput::make('cube_text')
                            ->label('3D Cube Text')
                            ->default('PHP')
                            ->maxLength(10),
                    ]),

                FormSection::make('Contact Settings')
                    ->hidden(fn($record) => $record?->key !== 'contact')
                    ->statePath('data')
                    ->schema([
                        TextInput::make('email')
                            ->email()
                            ->required(),
                        Textarea::make('description')
                            ->rows(3),
                    ]),

                FormSection::make('Introduction Settings')
                    ->hidden(fn($record) => $record?->key !== 'introduction')
                    ->statePath('data')
                    ->schema([
                        FileUpload::make('intro_image')
                            ->image()
                            ->disk('public')
                            ->directory('sections/introduction')
                            ->visibility('public')
                            ->maxSize(10240)
                            ->required(),
                        Textarea::make('intro_description')
                            ->label('Introduction Text')
                            ->rows(5)
                            ->required(),
                        TextInput::make('intro_attribution')
                            ->label('Attribution Text'),

                        Select::make('bg_type')
                            ->label('Background Type')
                            ->options([
                                'default' => 'Default (Split Color)',
                                'image' => 'Custom Image',
                                'video' => 'Custom Video',
                            ])
                            ->default('default')
                            ->live(),

                        FileUpload::make('bg_image')
                            ->label('Background Image')
                            ->image()
                            ->disk('public')
                            ->directory('sections/introduction/bg')
                            ->visibility('public')
                            ->maxSize(10240)
                            ->visible(fn($get) => $get('bg_type') === 'image')
                            ->required(fn($get) => $get('bg_type') === 'image'),

                        FileUpload::make('bg_video')
                            ->label('Background Video')
                            ->acceptedFileTypes(['video/mp4', 'video/webm', 'video/ogg'])
                            ->disk('public')
                            ->directory('sections/introduction/bg')
                            ->visibility('public')
                            ->maxSize(51200)
                            ->visible(fn($get) => $get('bg_type') === 'video')
                            ->required(fn($get) => $get('bg_type') === 'video'),
                    ]),

                FormSection::make('Layout Settings')
                    ->description('Configuration for section appearance and grid layout')
                    ->statePath('layout')
                    ->schema([
                        Select::make('columns')
                            ->label('Grid Columns')
                            ->options([
                                1 => '1 Column',
                                2 => '2 Columns',
                                3 => '3 Columns (Default)',
                                4 => '4 Columns',
                            ])
                            ->default(3),
                        Select::make('gap')
                            ->label('Grid Gap')
                            ->options([
                                '0' => 'None (gap-0)',
                                '1' => 'Tight (gap-1, Default)',
                                '2' => 'Compact (gap-2)',
                                '4' => 'Medium (gap-4)',
                                '8' => 'Wide (gap-8)',
                            ])
                            ->default('1'),
                        Select::make('padding')
                            ->label('Vertical Padding')
                            ->options([
                                'py-20' => 'Compact (py-20)',
                                'py-40' => 'Default (py-40)',
                                'py-60' => 'Spacious (py-60)',
                            ])
                            ->default('py-40'),
                        Toggle::make('full_width')
                            ->label('Full Width Container')
                            ->default(false),
                    ]),
            ]);
    }
}
