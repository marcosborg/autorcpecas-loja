<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\OrderItem;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SalesOverviewWidget extends StatsOverviewWidget
{
    protected ?string $heading = 'Resumo de vendas';

    protected ?string $pollingInterval = '60s';

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $totalOrders = (int) Order::query()->count();
        $ordersAwaitingPayment = (int) Order::query()->where('status', 'awaiting_payment')->count();
        $paidOrders = (int) Order::query()->whereIn('status', ['paid', 'processing', 'shipped', 'completed'])->count();

        $revenueGross = (float) Order::query()
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->sum('total_inc_vat');

        $revenueThisMonth = (float) Order::query()
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->whereBetween('placed_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('total_inc_vat');

        $itemsSold = (int) OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNotIn('orders.status', ['cancelled', 'refunded'])
            ->sum('order_items.quantity');

        return [
            Stat::make('Encomendas', number_format($totalOrders, 0, ',', ' '))
                ->description('Pagas/processo: '.number_format($paidOrders, 0, ',', ' '))
                ->icon('heroicon-m-shopping-bag')
                ->color('primary'),
            Stat::make('A aguardar pagamento', number_format($ordersAwaitingPayment, 0, ',', ' '))
                ->description('Requer confirmação de pagamento')
                ->icon('heroicon-m-clock')
                ->color('warning'),
            Stat::make('Vendas brutas', number_format($revenueGross, 2, ',', ' ').' EUR')
                ->description('Mês atual: '.number_format($revenueThisMonth, 2, ',', ' ').' EUR')
                ->icon('heroicon-m-banknotes')
                ->color('success'),
            Stat::make('Peças vendidas', number_format($itemsSold, 0, ',', ' '))
                ->description('Exclui encomendas canceladas/reembolsadas')
                ->icon('heroicon-m-cube')
                ->color('gray'),
        ];
    }
}

