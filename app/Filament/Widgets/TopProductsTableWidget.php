<?php

namespace App\Filament\Widgets;

use App\Models\OrderItem;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class TopProductsTableWidget extends TableWidget
{
    protected static ?string $heading = 'Top produtos vendidos';

    protected int | string | array $columnSpan = 2;

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getBaseQuery())
            ->defaultSort('qty_sold', 'desc')
            ->paginated([10])
            ->columns([
                TextColumn::make('title')
                    ->label('Produto')
                    ->searchable(),
                TextColumn::make('reference')
                    ->label('Ref.')
                    ->toggleable(),
                TextColumn::make('qty_sold')
                    ->label('Qtd vendida')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('revenue_inc_vat')
                    ->label('Receita c/ IVA')
                    ->money('EUR')
                    ->sortable(),
            ]);
    }

    public function getTableRecordKey(\Illuminate\Database\Eloquent\Model|array $record): string
    {
        $productKey = trim((string) data_get($record, 'product_key', ''));
        if ($productKey !== '') {
            return $productKey;
        }

        if (is_array($record)) {
            return (string) ($record['id'] ?? spl_object_id((object) $record));
        }

        return (string) $record->getKey();
    }

    private function getBaseQuery(): Builder
    {
        return OrderItem::query()
            ->selectRaw('MIN(order_items.id) as id')
            ->selectRaw('order_items.product_key')
            ->selectRaw('MAX(order_items.title) as title')
            ->selectRaw('MAX(order_items.reference) as reference')
            ->selectRaw('SUM(order_items.quantity) as qty_sold')
            ->selectRaw('SUM(order_items.line_total_ex_vat * (1 + COALESCE(orders.vat_rate, 23) / 100)) as revenue_inc_vat')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNotIn('orders.status', ['cancelled', 'refunded'])
            ->groupBy('order_items.product_key');
    }
}
