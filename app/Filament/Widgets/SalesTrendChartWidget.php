<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class SalesTrendChartWidget extends ChartWidget
{
    protected ?string $heading = 'Tendência de vendas (30 dias)';

    protected ?string $pollingInterval = '120s';

    protected function getType(): string
    {
        return 'line';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $start = now()->subDays(29)->startOfDay();
        $end = now()->endOfDay();

        $rows = Order::query()
            ->selectRaw('DATE(COALESCE(placed_at, created_at)) as order_date')
            ->selectRaw('SUM(total_inc_vat) as total_revenue')
            ->selectRaw('COUNT(*) as total_orders')
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->whereBetween(DB::raw('COALESCE(placed_at, created_at)'), [$start, $end])
            ->groupBy('order_date')
            ->orderBy('order_date')
            ->get();

        $rowsByDate = $rows->keyBy('order_date');
        $labels = [];
        $revenue = [];
        $orders = [];

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dateKey = $date->toDateString();
            $row = $rowsByDate->get($dateKey);

            $labels[] = $date->format('d/m');
            $revenue[] = round((float) ($row->total_revenue ?? 0), 2);
            $orders[] = (int) ($row->total_orders ?? 0);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Vendas (EUR)',
                    'data' => $revenue,
                    'borderColor' => '#700000',
                    'backgroundColor' => 'rgba(112, 0, 0, 0.12)',
                    'yAxisID' => 'y',
                    'tension' => 0.25,
                    'fill' => true,
                ],
                [
                    'label' => 'Encomendas',
                    'data' => $orders,
                    'borderColor' => '#1f2937',
                    'backgroundColor' => 'rgba(31, 41, 55, 0.12)',
                    'yAxisID' => 'y1',
                    'tension' => 0.2,
                    'fill' => false,
                ],
            ],
            'labels' => $labels,
        ];
    }

    /**
     * @return array<string, mixed> | null
     */
    protected function getOptions(): ?array
    {
        return [
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    'display' => true,
                ],
            ],
            'scales' => [
                'y' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'left',
                    'title' => [
                        'display' => true,
                        'text' => 'EUR',
                    ],
                ],
                'y1' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'right',
                    'grid' => [
                        'drawOnChartArea' => false,
                    ],
                    'title' => [
                        'display' => true,
                        'text' => 'Encomendas',
                    ],
                ],
            ],
        ];
    }
}

