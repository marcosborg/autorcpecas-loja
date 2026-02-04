<?php

namespace App\Filament\Resources\Banners\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class BannerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Toggle::make('is_active')
                    ->label('Ativo')
                    ->default(true),
                TextInput::make('sort_order')
                    ->label('Ordem')
                    ->numeric()
                    ->default(0),
                TextInput::make('title')
                    ->label('Título')
                    ->maxLength(255),
                TextInput::make('subtitle')
                    ->label('Subtítulo')
                    ->maxLength(255),
                FileUpload::make('image_path')
                    ->label('Imagem')
                    ->disk('public')
                    ->directory('banners')
                    ->image()
                    ->imagePreviewHeight('180')
                    ->maxSize(4096),
                TextInput::make('cta_text')
                    ->label('Texto do botão')
                    ->maxLength(80),
                TextInput::make('cta_url')
                    ->label('Link do botão')
                    ->url()
                    ->maxLength(255),
            ]);
    }
}

