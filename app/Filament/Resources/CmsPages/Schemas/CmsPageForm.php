<?php

namespace App\Filament\Resources\CmsPages\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class CmsPageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('Titulo')
                    ->required()
                    ->maxLength(180)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (string $operation, $state, $set): void {
                        if ($operation !== 'create') {
                            return;
                        }

                        $set('slug', Str::slug((string) $state));
                    }),
                TextInput::make('slug')
                    ->label('Slug')
                    ->required()
                    ->maxLength(180)
                    ->unique('cms_pages', 'slug', ignoreRecord: true),
                FileUpload::make('featured_image_path')
                    ->label('Imagem destaque')
                    ->disk('public')
                    ->directory('cms/pages')
                    ->image()
                    ->imagePreviewHeight('180')
                    ->maxSize(4096),
                RichEditor::make('content')
                    ->label('Texto')
                    ->columnSpanFull()
                    ->toolbarButtons([
                        'bold',
                        'italic',
                        'underline',
                        'strike',
                        'h2',
                        'h3',
                        'bulletList',
                        'orderedList',
                        'blockquote',
                        'link',
                        'undo',
                        'redo',
                    ]),
                Textarea::make('google_maps_embed_url')
                    ->label('URL embed Google Maps')
                    ->rows(2)
                    ->maxLength(1200)
                    ->placeholder('https://www.google.com/maps/embed?...')
                    ->helperText('Podes colar o iframe completo ou apenas o URL do src.')
                    ->dehydrateStateUsing(fn (?string $state): ?string => self::normalizeMapEmbedInput($state)),
                Toggle::make('show_contact_button')
                    ->label('Mostrar botao de contacto')
                    ->default(false)
                    ->live(),
                TextInput::make('contact_button_label')
                    ->label('Texto do botao de contacto')
                    ->maxLength(80)
                    ->default('Falar connosco')
                    ->visible(fn ($get): bool => (bool) $get('show_contact_button')),
                Toggle::make('is_published')
                    ->label('Publicada')
                    ->default(true),
                DateTimePicker::make('published_at')
                    ->label('Data de publicacao'),
                TextInput::make('sort_order')
                    ->label('Ordem')
                    ->numeric()
                    ->default(0),
            ]);
    }

    private static function normalizeMapEmbedInput(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/<iframe[^>]*src=[\"\']([^\"\']+)[\"\']/i', $value, $matches) === 1) {
            return trim((string) ($matches[1] ?? ''));
        }

        return $value;
    }
}
