<?php

namespace App\Filament\Resources\Orders;

use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Schemas\OrderForm;
use App\Filament\Resources\Orders\Tables\OrdersTable;
use App\Models\Order;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';

    protected static string|UnitEnum|null $navigationGroup = 'Checkout';

    protected static ?string $navigationLabel = 'Encomendas';

    protected static ?string $pluralModelLabel = 'encomendas';

    protected static ?string $modelLabel = 'encomenda';

    protected static ?string $recordTitleAttribute = 'order_number';

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            'awaiting_payment' => 'A aguardar pagamento',
            'paid' => 'Paga',
            'processing' => 'Em processamento',
            'shipped' => 'Enviada',
            'completed' => 'Concluida',
            'cancelled' => 'Cancelada',
            'refunded' => 'Reembolsada',
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return OrderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrdersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'edit' => EditOrder::route('/{record}/edit'),
        ];
    }
}
