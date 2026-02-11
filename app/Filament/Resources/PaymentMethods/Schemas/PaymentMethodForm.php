<?php

namespace App\Filament\Resources\PaymentMethods\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
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
                Section::make('Configuracao SIBS')
                    ->description('Preenche para os metodos com code sibs_card, sibs_mbway e sibs_multibanco.')
                    ->visible(fn ($get): bool => str_starts_with((string) $get('code'), 'sibs_'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('meta.client_id')
                            ->label('Client ID')
                            ->maxLength(255),
                        TextInput::make('meta.terminal_id')
                            ->label('Terminal ID')
                            ->maxLength(255),
                        TextInput::make('meta.bearer_token')
                            ->label('Bearer Token')
                            ->password()
                            ->revealable(),
                        TextInput::make('meta.webhook_secret')
                            ->label('Webhook Secret')
                            ->password()
                            ->revealable(),
                        TextInput::make('meta.server')
                            ->label('Server (TEST/LIVE)')
                            ->maxLength(20),
                        TextInput::make('meta.mode')
                            ->label('Mode (DB/PA)')
                            ->maxLength(20),
                        TextInput::make('meta.payment_entity')
                            ->label('Entidade MB')
                            ->maxLength(50),
                        TextInput::make('meta.payment_type')
                            ->label('Tipo MB')
                            ->maxLength(50),
                    ]),
                Section::make('Configuracao Transferencia Bancaria')
                    ->visible(fn ($get): bool => (string) $get('code') === 'bank_transfer')
                    ->schema([
                        TextInput::make('meta.owner')
                            ->label('Titular')
                            ->maxLength(255),
                        TextInput::make('meta.details')
                            ->label('Dados bancarios (IBAN/BIC)')
                            ->maxLength(1000),
                        TextInput::make('meta.address')
                            ->label('Morada/Balcao')
                            ->maxLength(255),
                    ]),
            ]);
    }
}
