<?php

namespace App\Filament\Resources\TechStacks\Schemas;

use Filament\Schemas\Schema;

class TechStackForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                \Filament\Forms\Components\FileUpload::make('image_path')
                    ->image()
                    ->disk('public')
                    ->directory('tech-stacks')
                    ->visibility('public')
                    ->maxSize(5120),
                \Filament\Forms\Components\TextInput::make('sort_order')
                    ->numeric()
                    ->default(0)
                    ->required(),
            ]);
    }
}
