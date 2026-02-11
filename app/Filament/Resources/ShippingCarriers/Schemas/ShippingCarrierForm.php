<?php

namespace App\Filament\Resources\ShippingCarriers\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ShippingCarrierForm
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
                Select::make('rate_basis')
                    ->label('Base de calculo')
                    ->options([
                        'weight' => 'Peso',
                        'price' => 'Preco',
                    ])
                    ->required(),
                TextInput::make('transit_delay')
                    ->label('Prazo de entrega')
                    ->maxLength(120),
                TextInput::make('free_shipping_over_ex_vat')
                    ->label('Portes gratis acima de (sem IVA)')
                    ->numeric()
                    ->suffix('EUR'),
                TextInput::make('position')
                    ->label('Posicao')
                    ->numeric()
                    ->default(0),
                Toggle::make('is_pickup')
                    ->label('Levantamento em loja')
                    ->default(false),
                Toggle::make('active')
                    ->label('Ativa')
                    ->default(true),
            ]);
    }
}

