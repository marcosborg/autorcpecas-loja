<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\PaymentMethod;
use Illuminate\Support\Facades\Http;

class SibsCheckoutService
{
    private const API_URL_LIVE = 'https://api.sibspayments.com/api/v2/payments';
    private const API_URL_TEST = 'https://spg.qly.site1.sibs.pt/api/v2/payments';
    private const WIDGET_URL_LIVE = 'https://api.sibspayments.com/assets/js/widget.js?id=';
    private const WIDGET_URL_TEST = 'https://spg.qly.site1.sibs.pt/assets/js/widget.js?id=';

    /**
     * @return array{message: string, redirect_url?: string}
     */
    public function startCheckoutForOrder(Order $order, ?int $actorUserId = null): array
    {
        $snapshot = is_array($order->payment_method_snapshot) ? $order->payment_method_snapshot : [];
        $code = trim((string) ($snapshot['code'] ?? ''));
        if ($code === '' || ! str_starts_with($code, 'sibs_')) {
            throw new \RuntimeException('Metodo de pagamento SIBS invalido para esta encomenda.');
        }

        $meta = $this->resolveMeta($code, $snapshot);
        $clientId = trim((string) ($meta['client_id'] ?? ''));
        $terminalId = trim((string) ($meta['terminal_id'] ?? ''));
        $bearerToken = trim((string) ($meta['bearer_token'] ?? ''));

        if ($clientId === '' || $terminalId === '' || $bearerToken === '') {
            throw new \RuntimeException('Credenciais SIBS incompletas (client_id, terminal_id ou bearer_token).');
        }

        $serverMode = mb_strtoupper(trim((string) ($meta['server'] ?? 'TEST')), 'UTF-8');
        $isLive = $serverMode === 'LIVE';
        $apiUrl = $isLive ? self::API_URL_LIVE : self::API_URL_TEST;
        $widgetBase = $isLive ? self::WIDGET_URL_LIVE : self::WIDGET_URL_TEST;

        $paymentMethod = $this->mapPaymentMethod($code);
        $paymentType = $this->mapPaymentType($code, trim((string) ($meta['mode'] ?? 'DB')));

        $payload = $this->buildPayload(
            $order->loadMissing('user'),
            $paymentMethod,
            $paymentType,
            $terminalId,
            $meta,
        );

        $authToken = preg_replace('/^Bearer\s+/i', '', $bearerToken) ?: $bearerToken;

        $response = Http::timeout(30)
            ->withHeaders([
                'Authorization' => 'Bearer '.$authToken,
                'X-IBM-Client-ID' => $clientId,
                'X-IBM-Client-Id' => $clientId,
                'Content-Type' => 'application/json;charset=UTF-8',
            ])
            ->post($apiUrl, $payload);

        if (! $response->successful()) {
            $body = $response->json();
            $statusMsg = is_array($body)
                ? trim((string) (
                    data_get($body, 'returnStatus.statusMsg')
                    ?? data_get($body, 'returnStatus.statusDescription')
                    ?? data_get($body, 'message')
                    ?? ''
                ))
                : '';

            $suffix = $statusMsg !== '' ? (' - '.$statusMsg) : '';
            throw new \RuntimeException('Falha na comunicacao com a SIBS (HTTP '.$response->status().')'.$suffix.'.');
        }

        $body = $response->json();
        if (! is_array($body)) {
            throw new \RuntimeException('Resposta invalida da SIBS.');
        }

        $statusCode = trim((string) data_get($body, 'returnStatus.statusCode', ''));
        if ($statusCode !== '' && $statusCode !== '000') {
            $statusMsg = trim((string) (
                data_get($body, 'returnStatus.statusMsg')
                ?? data_get($body, 'returnStatus.statusDescription')
                ?? 'Erro na criacao do checkout SIBS.'
            ));
            throw new \RuntimeException($statusMsg !== '' ? $statusMsg : 'Erro na criacao do checkout SIBS.');
        }

        $transactionId = trim((string) (
            data_get($body, 'transactionID')
            ?? data_get($body, 'id')
            ?? ''
        ));

        $execution = [
            'provider' => 'sibs',
            'payment_method' => $paymentMethod,
            'payment_type' => $paymentType,
            'server_mode' => $serverMode,
            'transaction_id' => $transactionId,
            'form_context' => (string) data_get($body, 'formContext', ''),
            'signature' => (string) data_get($body, 'transactionSignature', ''),
            'widget_url' => $transactionId !== '' ? ($widgetBase.$transactionId) : '',
            'response' => $body,
            'created_at' => now()->toIso8601String(),
        ];

        $snapshot['sibs_execution'] = $execution;
        $snapshot = $this->hydrateReferenceDetails($snapshot, $body, (float) $order->total_inc_vat, (string) $order->currency);

        $order->payment_method_snapshot = $snapshot;
        $order->save();

        OrderStatusHistory::query()->create([
            'order_id' => $order->id,
            'status' => (string) $order->status,
            'note' => 'Checkout SIBS iniciado'.($transactionId !== '' ? (' (Tx: '.$transactionId.')') : '').'.',
            'created_by_user_id' => $actorUserId,
        ]);

        if (in_array($code, ['sibs_mbway', 'sibs_card'], true) && $execution['form_context'] !== '' && $execution['signature'] !== '' && $execution['widget_url'] !== '') {
            return [
                'message' => 'Checkout SIBS iniciado. Continua o pagamento.',
                'redirect_url' => url('/loja/conta/encomendas/'.$order->id.'/pay/sibs'),
            ];
        }

        if ($code === 'sibs_multibanco') {
            return [
                'message' => 'Referencia Multibanco gerada com sucesso.',
            ];
        }

        return [
            'message' => 'Pagamento SIBS iniciado.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function widgetData(Order $order): array
    {
        $snapshot = is_array($order->payment_method_snapshot) ? $order->payment_method_snapshot : [];
        $execution = is_array($snapshot['sibs_execution'] ?? null) ? $snapshot['sibs_execution'] : [];
        if ($execution === []) {
            throw new \RuntimeException('Nao existe checkout SIBS iniciado para esta encomenda.');
        }

        $formConfig = [
            'paymentMethodList' => [(string) ($execution['payment_method'] ?? 'CARD')],
            'amount' => [
                'value' => round((float) $order->total_inc_vat, 2),
                'currency' => (string) $order->currency,
            ],
            'language' => 'pt',
            'redirectUrl' => url('/loja/conta/encomendas/'.$order->id),
            'customerData' => null,
        ];

        return [
            'widget_url' => (string) ($execution['widget_url'] ?? ''),
            'form_context' => (string) ($execution['form_context'] ?? ''),
            'signature' => (string) ($execution['signature'] ?? ''),
            'form_config' => $formConfig,
            'payment_method' => (string) ($execution['payment_method'] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function resolveMeta(string $code, array $snapshot): array
    {
        $existingMeta = is_array($snapshot['meta'] ?? null) ? $snapshot['meta'] : [];

        $method = PaymentMethod::query()->where('code', $code)->first();
        $methodMeta = is_array($method?->meta) ? $method->meta : [];

        return array_merge($methodMeta, $existingMeta);
    }

    private function mapPaymentMethod(string $code): string
    {
        return match ($code) {
            'sibs_card' => 'CARD',
            'sibs_mbway' => 'MBWAY',
            'sibs_multibanco' => 'REFERENCE',
            default => throw new \RuntimeException('Metodo SIBS nao suportado: '.$code),
        };
    }

    private function mapPaymentType(string $code, string $mode): string
    {
        if ($code === 'sibs_multibanco') {
            return 'AUTH';
        }

        $mode = mb_strtoupper(trim($mode), 'UTF-8');

        return match ($mode) {
            'PA' => 'AUTH',
            'PA.DB' => 'CAPT',
            'RF' => 'RFND',
            default => 'PURS',
        };
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function buildPayload(Order $order, string $paymentMethod, string $paymentType, string $terminalId, array $meta): array
    {
        $shipping = is_array($order->shipping_address_snapshot) ? $order->shipping_address_snapshot : [];
        $billing = is_array($order->billing_address_snapshot) ? $order->billing_address_snapshot : [];

        $customerName = trim(((string) ($shipping['first_name'] ?? '')).' '.((string) ($shipping['last_name'] ?? '')));
        $customerEmail = trim((string) ($order->user?->email ?? ''));
        $customerPhone = trim((string) (($shipping['phone'] ?? '') ?: ($billing['phone'] ?? '') ?: ($order->user?->phone ?? '')));

        $amount = round((float) $order->total_inc_vat, 2);
        $currency = (string) $order->currency;
        $merchantTransactionId = (string) $order->order_number;

        $payload = [
            'merchant' => [
                'terminalId' => (int) $terminalId,
                'channel' => 'web',
                'merchantTransactionId' => $merchantTransactionId,
            ],
            'customer' => [
                'customerInfo' => [
                    'customerName' => $customerName !== '' ? $customerName : 'Cliente',
                    'customerEmail' => $customerEmail,
                    'shippingAddress' => [
                        'street1' => (string) ($shipping['address_line1'] ?? ''),
                        'street2' => (string) ($shipping['address_line2'] ?? ''),
                        'city' => (string) ($shipping['city'] ?? ''),
                        'postcode' => (string) ($shipping['postal_code'] ?? ''),
                        'country' => (string) ($shipping['country_iso2'] ?? 'PT'),
                    ],
                    'billingAddress' => [
                        'street1' => (string) ($billing['address_line1'] ?? ''),
                        'street2' => (string) ($billing['address_line2'] ?? ''),
                        'city' => (string) ($billing['city'] ?? ''),
                        'postcode' => (string) ($billing['postal_code'] ?? ''),
                        'country' => (string) ($billing['country_iso2'] ?? 'PT'),
                    ],
                ],
            ],
            'transaction' => [
                'transactionTimestamp' => now()->format('Y-m-d\TH:i:s'),
                'description' => 'Transaction for order number '.$merchantTransactionId.' terminalId='.$terminalId,
                'moto' => ((string) ($meta['moto'] ?? '0')) === '1',
                'paymentType' => $paymentType,
                'paymentMethod' => [$paymentMethod],
                'amount' => [
                    'value' => $amount,
                    'currency' => $currency,
                ],
            ],
            'info' => [
                'deviceInfo' => [
                    'applicationName' => 'Laravel',
                    'applicationVersion' => '1.0.0',
                ],
            ],
            'tokenisation' => [
                'tokenisationRequest' => [
                    'tokeniseCard' => false,
                ],
                'paymentTokens' => [],
            ],
        ];

        if ($customerPhone !== '') {
            $payload['customer']['customerInfo']['customerPhone'] = $customerPhone;
        }

        if ($paymentMethod === 'REFERENCE') {
            $initial = now();
            $expiryValue = (int) ($meta['payment_value'] ?? 0);
            $expiryType = mb_strtolower(trim((string) ($meta['payment_type'] ?? 'day')), 'UTF-8');
            $final = $initial->copy()->addDays(max(0, $expiryValue));
            if ($expiryType === 'hour') {
                $final = $initial->copy()->addHours(max(0, $expiryValue));
            } elseif ($expiryType === 'minute') {
                $final = $initial->copy()->addMinutes(max(0, $expiryValue));
            } elseif ($expiryType === 'month') {
                $final = $initial->copy()->addMonths(max(0, $expiryValue));
            }

            $payload['transaction']['paymentReference'] = [
                'initialDatetime' => $initial->format('Y-m-d\TH:i:s'),
                'finalDatetime' => $final->format('Y-m-d\TH:i:s'),
                'maxAmount' => [
                    'value' => $amount,
                    'currency' => $currency,
                ],
                'minAmount' => [
                    'value' => $amount,
                    'currency' => $currency,
                ],
                'entity' => trim((string) ($meta['payment_entity'] ?? '')),
            ];
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function hydrateReferenceDetails(array $snapshot, array $body, float $defaultAmount, string $defaultCurrency): array
    {
        if ((string) ($snapshot['code'] ?? '') !== 'sibs_multibanco') {
            return $snapshot;
        }

        $entity = trim((string) data_get($body, 'paymentReference.entity', ''));
        $reference = preg_replace('/\D+/', '', (string) data_get($body, 'paymentReference.reference', ''));
        $reference = is_string($reference) ? trim($reference) : '';
        $amount = (float) (data_get($body, 'paymentReference.amount.value') ?? $defaultAmount);
        $currency = trim((string) (data_get($body, 'paymentReference.amount.currency') ?? $defaultCurrency));
        $expireDate = trim((string) data_get($body, 'paymentReference.expireDate', ''));

        $display = $reference;
        if (strlen($reference) === 9) {
            $display = substr($reference, 0, 3).' '.substr($reference, 3, 3).' '.substr($reference, 6, 3);
        }

        $snapshot['payment_instructions'] = [
            'entity' => $entity,
            'reference' => $reference,
            'reference_display' => $display,
            'amount' => round($amount, 2),
            'currency' => $currency !== '' ? $currency : 'EUR',
            'expire_date' => $expireDate,
        ];

        return $snapshot;
    }
}
