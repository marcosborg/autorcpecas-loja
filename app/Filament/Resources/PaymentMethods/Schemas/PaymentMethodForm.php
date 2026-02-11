<?php

namespace App\Filament\Resources\PaymentMethods\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PaymentMethodForm
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
                TextInput::make('provider')
                    ->label('Fornecedor')
                    ->maxLength(120),
                Select::make('fee_type')
                    ->label('Tipo de taxa')
                    ->options([
                        'none' => 'Sem taxa',
                        'fixed' => 'Fixa',
                        'percent' => 'Percentagem',
                    ])
                    ->required()
                    ->default('none'),
                TextInput::make('fee_value')
                    ->label('Valor da taxa')
                    ->numeric()
                    ->default(0),
                TextInput::make('position')
                    ->label('Posicao')
                    ->numeric()
                    ->default(0),
                Toggle::make('active')
                    ->label('Ativo')
                    ->default(true),
                Select::make('carriers')
                    ->label('Transportadoras permitidas')
                    ->relationship('carriers', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload(),
            ]);
    }
}

