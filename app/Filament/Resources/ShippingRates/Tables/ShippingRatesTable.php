<?php

namespace App\Filament\Resources\ShippingRates\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ShippingRatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('carrier.name')
                    ->label('Transportadora')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('zone.name')
                    ->label('Zona')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('calc_type')
                    ->label('Base')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'weight' ? 'Peso' : 'Preco'),
                TextColumn::make('range_from')
                    ->label('De')
                    ->sortable(),
                TextColumn::make('range_to')
                    ->label('Ate')
                    ->sortable(),
                TextColumn::make('price_ex_vat')
                    ->label('Preco')
                    ->money('EUR')
                    ->sortable(),
                IconColumn::make('active')
                    ->label('Ativa')
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

