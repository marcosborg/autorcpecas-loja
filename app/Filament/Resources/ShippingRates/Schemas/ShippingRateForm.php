<?php

namespace App\Filament\Resources\ShippingRates\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ShippingRateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('shipping_carrier_id')
                    ->label('Transportadora')
                    ->relationship('carrier', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('shipping_zone_id')
                    ->label('Zona')
                    ->relationship('zone', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('calc_type')
                    ->label('Tipo de calculo')
                    ->options([
                        'weight' => 'Peso',
                        'price' => 'Preco',
                    ])
                    ->required(),
                TextInput::make('range_from')
                    ->label('Intervalo de')
                    ->numeric()
                    ->required(),
                TextInput::make('range_to')
                    ->label('Intervalo ate')
                    ->numeric(),
                TextInput::make('price_ex_vat')
                    ->label('Preco (sem IVA)')
                    ->numeric()
                    ->required()
                    ->suffix('EUR'),
                TextInput::make('handling_fee_ex_vat')
                    ->label('Taxa de manuseamento (sem IVA)')
                    ->numeric()
                    ->default(0)
                    ->suffix('EUR'),
                Toggle::make('active')
                    ->label('Ativa')
                    ->default(true),
            ]);
    }
}

