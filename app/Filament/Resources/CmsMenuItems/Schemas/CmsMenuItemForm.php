<?php

namespace App\Filament\Resources\CmsMenuItems\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CmsMenuItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('label')
                    ->label('Texto do link')
                    ->required()
                    ->maxLength(120),
                Select::make('link_type')
                    ->label('Tipo de link')
                    ->options([
                        'url' => 'URL personalizada',
                        'cms_page' => 'Pagina CMS',
                    ])
                    ->required()
                    ->default('url')
                    ->live(),
                TextInput::make('url')
                    ->label('URL')
                    ->maxLength(255)
                    ->placeholder('/marcas ou https://...')
                    ->visible(fn ($get): bool => (string) $get('link_type') === 'url')
                    ->required(fn ($get): bool => (string) $get('link_type') === 'url'),
                Select::make('cms_page_id')
                    ->label('Pagina CMS')
                    ->relationship('page', 'title', fn ($query) => $query->orderBy('title'))
                    ->searchable()
                    ->preload()
                    ->visible(fn ($get): bool => (string) $get('link_type') === 'cms_page')
                    ->required(fn ($get): bool => (string) $get('link_type') === 'cms_page'),
                Toggle::make('open_in_new_tab')
                    ->label('Abrir em nova aba')
                    ->default(false),
                Toggle::make('is_button')
                    ->label('Estilo botao (CTA)')
                    ->default(false),
                Toggle::make('is_active')
                    ->label('Ativo')
                    ->default(true),
                TextInput::make('sort_order')
                    ->label('Ordem')
                    ->numeric()
                    ->default(0),
            ]);
    }
}

