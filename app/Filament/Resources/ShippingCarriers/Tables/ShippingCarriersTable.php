<?php

namespace App\Filament\Resources\ShippingCarriers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ShippingCarriersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('code')
                    ->label('Codigo')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('rate_basis')
                    ->label('Base')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'weight' ? 'Peso' : 'Preco'),
                TextColumn::make('free_shipping_over_ex_vat')
                    ->label('Portes gratis')
                    ->money('EUR')
                    ->sortable(),
                IconColumn::make('is_pickup')
                    ->label('Pickup')
                    ->boolean(),
                IconColumn::make('active')
                    ->label('Ativa')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('position')
                    ->label('Posicao')
                    ->sortable(),
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

