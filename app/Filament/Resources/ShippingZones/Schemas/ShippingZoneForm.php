<?php

namespace App\Filament\Resources\ShippingZones\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ShippingZoneForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label('Codigo')
                    ->required()
                    ->maxLength(100),
                TextInput::make('name')
                    ->label('Nome')
                    ->required()
                    ->maxLength(120),
                TextInput::make('position')
                    ->label('Posicao')
                    ->numeric()
                    ->default(0),
                Toggle::make('active')
                    ->label('Ativa')
                    ->default(true),
                Repeater::make('countries')
                    ->label('Paises (ISO2)')
                    ->relationship()
                    ->schema([
                        TextInput::make('country_iso2')
                            ->label('ISO2')
                            ->required()
                            ->maxLength(2)
                            ->formatStateUsing(fn (?string $state): string => strtoupper((string) $state))
                            ->dehydrateStateUsing(fn (?string $state): string => strtoupper(trim((string) $state))),
                    ])
                    ->defaultItems(0)
                    ->columnSpanFull(),
            ]);
    }
}
