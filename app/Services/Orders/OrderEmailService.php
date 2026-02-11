<?php

namespace App\Services\Orders;

use App\Mail\OrderLifecycleMail;
use App\Models\Order;
use App\Support\OrderStatuses;
use Illuminate\Support\Facades\Mail;

class OrderEmailService
{
    public function sendOrderCreated(Order $order): void
    {
        $order->loadMissing('user');

        $email = trim((string) ($order->user?->email ?? ''));
        if ($email === '') {
            return;
        }

        $paymentMethod = trim((string) data_get($order->payment_method_snapshot, 'name', '-'));
        $shippingMethod = trim((string) data_get($order->shipping_method_snapshot, 'name', '-'));

        $context = [
            'subject' => 'Encomenda criada: '.$order->order_number,
            'title' => 'Recebemos a tua encomenda',
            'intro' => 'A encomenda foi criada com sucesso e está registada no teu painel de cliente.',
            'highlight' => 'Estado atual: '.OrderStatuses::label((string) $order->status),
            'button_label' => 'Acompanhar encomenda',
            'button_url' => url('/loja/conta/encomendas/'.$order->id),
            'rows' => [
                ['label' => 'Encomenda', 'value' => (string) $order->order_number],
                ['label' => 'Estado', 'value' => OrderStatuses::label((string) $order->status)],
                ['label' => 'Pagamento', 'value' => $paymentMethod !== '' ? $paymentMethod : '-'],
                ['label' => 'Transporte', 'value' => $shippingMethod !== '' ? $shippingMethod : '-'],
                ['label' => 'Total c/ IVA', 'value' => number_format((float) $order->total_inc_vat, 2, ',', ' ').' '.$order->currency],
            ],
        ];

        $this->deliver($email, $order, $context);
    }

    public function sendPaymentUpdated(Order $order, ?string $previousStatus = null): void
    {
        $order->loadMissing('user');

        $email = trim((string) ($order->user?->email ?? ''));
        if ($email === '') {
            return;
        }

        $current = (string) $order->status;
        $previous = (string) ($previousStatus ?? '');
        $paymentMethod = trim((string) data_get($order->payment_method_snapshot, 'name', '-'));

        $highlight = 'Estado de pagamento: '.OrderStatuses::label($current);
        if ($previous !== '') {
            $highlight = OrderStatuses::label($previous).' -> '.OrderStatuses::label($current);
        }

        $context = [
            'subject' => 'Atualização de pagamento: '.$order->order_number,
            'title' => 'Atualização de pagamento da encomenda',
            'intro' => 'Temos uma atualização sobre o pagamento da tua encomenda.',
            'highlight' => $highlight,
            'button_label' => 'Ver detalhe de pagamento',
            'button_url' => url('/loja/conta/encomendas/'.$order->id),
            'rows' => [
                ['label' => 'Encomenda', 'value' => (string) $order->order_number],
                ['label' => 'Método de pagamento', 'value' => $paymentMethod !== '' ? $paymentMethod : '-'],
                ['label' => 'Estado atual', 'value' => OrderStatuses::label($current)],
                ['label' => 'Total c/ IVA', 'value' => number_format((float) $order->total_inc_vat, 2, ',', ' ').' '.$order->currency],
            ],
        ];

        $this->deliver($email, $order, $context);
    }

    public function sendStatusUpdated(Order $order, string $previousStatus): void
    {
        $order->loadMissing('user');

        $email = trim((string) ($order->user?->email ?? ''));
        if ($email === '') {
            return;
        }

        $current = (string) $order->status;

        $context = [
            'subject' => 'Encomenda '.$order->order_number.' atualizada para: '.OrderStatuses::label($current),
            'title' => 'Estado da encomenda atualizado',
            'intro' => 'A tua encomenda foi atualizada no nosso sistema.',
            'highlight' => OrderStatuses::label($previousStatus).' -> '.OrderStatuses::label($current),
            'button_label' => 'Ver detalhe da encomenda',
            'button_url' => url('/loja/conta/encomendas/'.$order->id),
            'rows' => [
                ['label' => 'Encomenda', 'value' => (string) $order->order_number],
                ['label' => 'Estado anterior', 'value' => OrderStatuses::label($previousStatus)],
                ['label' => 'Novo estado', 'value' => OrderStatuses::label($current)],
                ['label' => 'Total c/ IVA', 'value' => number_format((float) $order->total_inc_vat, 2, ',', ' ').' '.$order->currency],
            ],
        ];

        $this->deliver($email, $order, $context);
    }

    private function deliver(string $email, Order $order, array $context): void
    {
        try {
            Mail::to($email)->send(new OrderLifecycleMail($order, $context));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}

