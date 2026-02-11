<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\PaymentMethod;
use App\Services\Orders\OrderEmailService;

class SibsWebhookService
{
    public function __construct(
        private readonly OrderEmailService $orderEmails,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{ok: bool, status: int, message: string, order_id?: int}
     */
    public function handle(array $payload, ?string $providedSecret = null): array
    {
        $order = $this->resolveOrder($payload);
        if (! $order) {
            return ['ok' => false, 'status' => 404, 'message' => 'Encomenda não encontrada.'];
        }

        $paymentCode = (string) data_get($order->payment_method_snapshot, 'code', '');
        if ($paymentCode === '' || ! str_starts_with($paymentCode, 'sibs_')) {
            return ['ok' => false, 'status' => 422, 'message' => 'Encomenda sem método SIBS associado.'];
        }

        $method = PaymentMethod::query()
            ->where('code', $paymentCode)
            ->where('active', true)
            ->first();

        if (! $method) {
            return ['ok' => false, 'status' => 422, 'message' => 'Método de pagamento SIBS inválido/inativo.'];
        }

        $expectedSecret = trim((string) data_get($method->meta, 'webhook_secret', ''));
        if ($expectedSecret !== '') {
            $providedSecret = trim((string) $providedSecret);
            if ($providedSecret === '' || ! hash_equals($expectedSecret, $providedSecret)) {
                return ['ok' => false, 'status' => 401, 'message' => 'Webhook secret inválido.'];
            }
        }

        $newStatus = $this->mapWebhookStatus($payload);
        if ($newStatus === null) {
            return ['ok' => false, 'status' => 422, 'message' => 'Status de pagamento não suportado.'];
        }

        $previousStatus = (string) $order->status;
        if ($previousStatus === $newStatus) {
            return [
                'ok' => true,
                'status' => 200,
                'message' => 'Status já atualizado.',
                'order_id' => (int) $order->id,
            ];
        }

        $transactionId = trim((string) (
            data_get($payload, 'transaction_id')
            ?? data_get($payload, 'transactionId')
            ?? data_get($payload, 'payment_id')
            ?? data_get($payload, 'id')
            ?? ''
        ));

        $note = 'Pagamento confirmado via webhook SIBS.';
        if ($transactionId !== '') {
            $note .= ' Tx: '.$transactionId;
        }

        Order::withoutEvents(function () use ($order, $newStatus, $note): void {
            $order->status = $newStatus;
            $order->save();

            OrderStatusHistory::query()->create([
                'order_id' => $order->id,
                'status' => $newStatus,
                'note' => $note,
                'created_by_user_id' => null,
            ]);
        });

        $order->refresh();
        $this->orderEmails->sendPaymentUpdated($order, $previousStatus);

        return [
            'ok' => true,
            'status' => 200,
            'message' => 'Pagamento confirmado e estado atualizado.',
            'order_id' => (int) $order->id,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function resolveOrder(array $payload): ?Order
    {
        $orderNumber = trim((string) (
            data_get($payload, 'order_number')
            ?? data_get($payload, 'orderNumber')
            ?? data_get($payload, 'merchant.order_number')
            ?? data_get($payload, 'merchantTransactionId')
            ?? ''
        ));

        if ($orderNumber !== '') {
            $order = Order::query()->where('order_number', $orderNumber)->first();
            if ($order) {
                return $order;
            }
        }

        $orderId = (int) (
            data_get($payload, 'order_id')
            ?? data_get($payload, 'orderId')
            ?? 0
        );

        if ($orderId > 0) {
            return Order::query()->find($orderId);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function mapWebhookStatus(array $payload): ?string
    {
        $raw = trim((string) (
            data_get($payload, 'payment_status')
            ?? data_get($payload, 'paymentStatus')
            ?? data_get($payload, 'status')
            ?? data_get($payload, 'event')
            ?? ''
        ));

        $raw = mb_strtolower($raw, 'UTF-8');

        if ($raw === '') {
            return null;
        }

        if (in_array($raw, ['paid', 'success', 'succeeded', 'completed', 'captured', 'authorized'], true)) {
            return 'paid';
        }

        if (in_array($raw, ['refunded', 'refund', 'partially_refunded'], true)) {
            return 'refunded';
        }

        if (in_array($raw, ['failed', 'declined', 'canceled', 'cancelled', 'expired'], true)) {
            return 'cancelled';
        }

        if (in_array($raw, ['pending', 'processing', 'awaiting_payment'], true)) {
            return 'awaiting_payment';
        }

        return null;
    }
}

