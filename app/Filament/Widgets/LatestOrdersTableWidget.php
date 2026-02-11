<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Support\OrderStatuses;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class LatestOrdersTableWidget extends TableWidget
{
    protected static ?string $heading = 'Últimas encomendas';

    protected int | string | array $columnSpan = 2;

    public function table(Table $table): Table
    {
        return $table
            ->query(Order::query()->with('user')->latest('id'))
            ->paginated([10])
            ->columns([
                TextColumn::make('order_number')
                    ->label('Encomenda')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Cliente')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->formatStateUsing(fn (?string $state): string => OrderStatuses::label($state))
                    ->badge()
                    ->sortable(),
                TextColumn::make('total_inc_vat')
                    ->label('Total c/ IVA')
                    ->money('EUR')
                    ->sortable(),
                TextColumn::make('placed_at')
                    ->label('Data')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ]);
    }
}

