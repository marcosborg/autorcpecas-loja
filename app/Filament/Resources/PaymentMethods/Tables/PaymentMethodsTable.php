<?php

namespace App\Filament\Resources\PaymentMethods\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaymentMethodsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('provider')
                    ->label('Fornecedor')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('fee_type')
                    ->label('Tipo de taxa')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'fixed' => 'Fixa',
                        'percent' => 'Percentagem',
                        default => 'Sem taxa',
                    }),
                TextColumn::make('fee_value')
                    ->label('Taxa')
                    ->sortable(),
                TextColumn::make('carriers_count')
                    ->label('Transportadoras')
                    ->counts('carriers'),
                IconColumn::make('active')
                    ->label('Ativo')
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

