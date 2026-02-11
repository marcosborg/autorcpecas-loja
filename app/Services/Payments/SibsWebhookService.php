<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\PaymentMethod;
use App\Services\Orders\OrderEmailService;
use Throwable;

class SibsWebhookService
{
    public function __construct(
        private readonly OrderEmailService $orderEmails,
    ) {
    }

    /**
     * @param array<string, mixed> $headers
     * @param array<string, mixed> $fallbackPayload
     * @return array{ok: bool, status: int, message: string, order_id?: int, response: array<string, mixed>}
     */
    public function handleIncoming(
        string $rawBody,
        array $headers = [],
        array $fallbackPayload = [],
        ?string $providedSecret = null,
    ): array {
        $resolved = $this->resolvePayload($rawBody, $headers, $fallbackPayload, $providedSecret);
        $payload = $resolved['payload'];
        $resolvedSecret = $resolved['matched_secret'] ?? null;
        $notificationId = (string) ($payload['notificationID'] ?? '');

        if ($payload === []) {
            return [
                'ok' => false,
                'status' => 500,
                'message' => 'Webhook sem payload valido.',
                'response' => [
                    'success' => false,
                    'error' => 500,
                    'message' => 'Webhook sem payload valido.',
                ],
            ];
        }

        $result = $this->handle($payload, $providedSecret ?: $resolvedSecret);

        return array_merge($result, [
            'response' => [
                'notificationID' => $notificationId,
                'statusCode' => $result['ok'] ? 200 : $result['status'],
                'statusMsg' => $result['ok'] ? 'Success' : 'Error',
                'message' => $result['message'],
                'order_id' => $result['order_id'] ?? null,
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{ok: bool, status: int, message: string, order_id?: int}
     */
    public function handle(array $payload, ?string $providedSecret = null): array
    {
        $order = $this->resolveOrder($payload);
        if (! $order) {
            // Acknowledge to avoid endless retries when SIBS sends duplicated/late events.
            return ['ok' => true, 'status' => 200, 'message' => 'Notificacao sem encomenda associada.'];
        }

        $paymentCode = (string) data_get($order->payment_method_snapshot, 'code', '');
        if ($paymentCode === '' || ! str_starts_with($paymentCode, 'sibs_')) {
            return ['ok' => false, 'status' => 422, 'message' => 'Encomenda sem metodo SIBS associado.'];
        }

        $method = PaymentMethod::query()
            ->where('code', $paymentCode)
            ->where('active', true)
            ->first();

        if (! $method) {
            return ['ok' => false, 'status' => 422, 'message' => 'Metodo de pagamento SIBS invalido/inativo.'];
        }

        $expectedSecret = trim((string) data_get($method->meta, 'webhook_secret', ''));
        if ($expectedSecret !== '') {
            $providedSecret = trim((string) $providedSecret);
            if ($providedSecret === '' || ! hash_equals($expectedSecret, $providedSecret)) {
                return ['ok' => false, 'status' => 401, 'message' => 'Webhook secret invalido.'];
            }
        }

        $newStatus = $this->mapWebhookStatus($payload);
        if ($newStatus === null) {
            return ['ok' => true, 'status' => 200, 'message' => 'Status de pagamento nao suportado, sem alteracoes.'];
        }

        $previousStatus = (string) $order->status;
        if ($previousStatus === $newStatus) {
            return [
                'ok' => true,
                'status' => 200,
                'message' => 'Status ja atualizado.',
                'order_id' => (int) $order->id,
            ];
        }

        $transactionId = trim((string) (
            data_get($payload, 'transaction_id')
            ?? data_get($payload, 'transactionId')
            ?? data_get($payload, 'transactionID')
            ?? data_get($payload, 'payment_id')
            ?? data_get($payload, 'id')
            ?? data_get($payload, 'transaction.transactionID')
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
            data_get($payload, 'merchant.merchantTransactionId')
            ?? data_get($payload, 'merchant.transactionId')
            ?? data_get($payload, 'order_number')
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
        $paymentStatus = trim((string) (
            data_get($payload, 'payment_status')
            ?? data_get($payload, 'paymentStatus')
            ?? data_get($payload, 'transaction.paymentStatus')
            ?? data_get($payload, 'status')
            ?? data_get($payload, 'event')
            ?? ''
        ));

        $paymentType = strtoupper(trim((string) (
            data_get($payload, 'paymentType')
            ?? data_get($payload, 'transaction.paymentType')
            ?? ''
        )));

        if ($paymentStatus === '') {
            return null;
        }

        return match ($paymentType) {
            'RFND' => $this->mapRefundStatus($paymentStatus),
            'CAUT', 'CMBW', 'CPRF', 'RVSL' => $this->mapCancelStatus($paymentStatus),
            'AUTH' => $this->mapAuthStatus($paymentStatus),
            'CAPT' => $this->mapCaptureStatus($paymentStatus),
            'PREF' => $this->mapReferenceStatus($paymentStatus),
            default => $this->mapPurchaseStatus($paymentStatus),
        };
    }

    private function mapPurchaseStatus(string $status): ?string
    {
        return match ($status) {
            'Success', 'Partial' => 'paid',
            'Pending', 'InProcessing' => 'awaiting_payment',
            'Declined', 'Timeout', 'Error' => 'cancelled',
            default => null,
        };
    }

    private function mapReferenceStatus(string $status): ?string
    {
        if ($status === 'Partial') {
            return 'awaiting_payment';
        }

        return $this->mapPurchaseStatus($status);
    }

    private function mapAuthStatus(string $status): ?string
    {
        return match ($status) {
            'Success', 'Partial', 'Pending', 'InProcessing' => 'awaiting_payment',
            'Declined', 'Timeout', 'Error' => 'cancelled',
            default => null,
        };
    }

    private function mapCaptureStatus(string $status): ?string
    {
        return match ($status) {
            'Success' => 'paid',
            'Declined', 'Timeout', 'Error' => 'cancelled',
            default => null,
        };
    }

    private function mapCancelStatus(string $status): ?string
    {
        return $status === 'Success' ? 'cancelled' : null;
    }

    private function mapRefundStatus(string $status): ?string
    {
        return $status === 'Success' ? 'refunded' : null;
    }

    /**
     * @param array<string, mixed> $headers
     * @param array<string, mixed> $fallbackPayload
     * @return array{payload: array<string, mixed>, matched_secret?: string|null}
     */
    private function resolvePayload(string $rawBody, array $headers, array $fallbackPayload, ?string $providedSecret): array
    {
        $initVector = $this->headerValue($headers, 'x-initialization-vector');
        $authTag = $this->headerValue($headers, 'x-authentication-tag');

        if ($initVector !== null && $authTag !== null && trim($rawBody) !== '') {
            $payload = $this->decryptPayload($rawBody, $initVector, $authTag, $providedSecret);
            if ($payload !== null) {
                return $payload;
            }
        }

        $decoded = json_decode($rawBody, true);
        if (is_array($decoded)) {
            return ['payload' => $decoded];
        }

        return ['payload' => is_array($fallbackPayload) ? $fallbackPayload : []];
    }

    /**
     * @param array<string, mixed> $headers
     */
    private function headerValue(array $headers, string $name): ?string
    {
        $name = strtolower($name);

        foreach ($headers as $key => $value) {
            if (strtolower((string) $key) !== $name) {
                continue;
            }

            if (is_array($value)) {
                $first = $value[0] ?? null;
                return $first !== null ? (string) $first : null;
            }

            return (string) $value;
        }

        return null;
    }

    /**
     * @return array{payload: array<string, mixed>, matched_secret: string}|null
     */
    private function decryptPayload(string $rawBody, string $ivHeader, string $tagHeader, ?string $providedSecret): ?array
    {
        $cipherText = $this->decodeWebhookBinary($rawBody);
        $iv = $this->decodeWebhookBinary($ivHeader);
        $authTag = $this->decodeWebhookBinary($tagHeader);

        if ($cipherText === null || $iv === null || $authTag === null) {
            return null;
        }

        $candidateSecrets = [];
        $providedSecret = trim((string) $providedSecret);
        if ($providedSecret !== '') {
            $candidateSecrets[] = $providedSecret;
        }

        $methodSecrets = PaymentMethod::query()
            ->where('active', true)
            ->where('code', 'like', 'sibs_%')
            ->get()
            ->pluck('meta.webhook_secret')
            ->filter(fn ($v): bool => is_string($v) && trim($v) !== '')
            ->map(fn ($v): string => trim((string) $v))
            ->unique()
            ->values()
            ->all();

        $candidateSecrets = array_values(array_unique(array_merge($candidateSecrets, $methodSecrets)));

        foreach ($candidateSecrets as $candidateSecret) {
            try {
                $key = $this->decodeWebhookSecret($candidateSecret);
                $decrypted = openssl_decrypt(
                    $cipherText,
                    'aes-256-gcm',
                    $key,
                    OPENSSL_RAW_DATA,
                    $iv,
                    $authTag
                );

                if (! is_string($decrypted) || $decrypted === '') {
                    continue;
                }

                $decoded = json_decode($decrypted, true);
                if (is_array($decoded)) {
                    return [
                        'payload' => $decoded,
                        'matched_secret' => $candidateSecret,
                    ];
                }
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }

    private function decodeWebhookSecret(string $secret): string
    {
        $decoded = $this->decodeWebhookBinary($secret);

        return $decoded ?? $secret;
    }

    private function decodeWebhookBinary(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if ((strlen($value) % 2) === 0 && ctype_xdigit($value)) {
            $decoded = hex2bin($value);
            if ($decoded !== false) {
                return $decoded;
            }
        }

        $b64 = base64_decode($value, true);
        if ($b64 !== false) {
            return $b64;
        }

        return $value;
    }
}

