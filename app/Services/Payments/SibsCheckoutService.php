<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\PaymentMethod;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
        $terminalId = trim((string) ($meta['terminal_id'] ?? ''));
        $bearerToken = trim((string) ($meta['bearer_token'] ?? ''));
        $credentialAttempts = $this->buildCredentialAttempts($meta);

        if ($terminalId === '' || $bearerToken === '' || $credentialAttempts === []) {
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

        $response = null;
        $usedAttempt = null;
        foreach ($credentialAttempts as $idx => $attempt) {
            $usedAttempt = $attempt;
            Log::info('SIBS checkout attempt', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'code' => $code,
                'attempt' => $idx + 1,
                'client_id' => $attempt['client_id'],
                'has_client_secret' => $attempt['client_secret'] !== '',
                'server_mode' => $serverMode,
            ]);

            $response = $this->requestCheckout(
                $apiUrl,
                $payload,
                $bearerToken,
                $attempt['client_id'],
                $attempt['client_secret'],
            );

            if ($response->successful()) {
                break;
            }

            // If credentials are rejected, retry with the next credential set.
            if ($response->status() !== 401) {
                break;
            }
        }

        if (! $response) {
            throw new \RuntimeException('Falha a preparar pedido para a SIBS.');
        }

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

            Log::warning('SIBS checkout failed', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'code' => $code,
                'http_status' => $response->status(),
                'server_mode' => $serverMode,
                'api_url' => $apiUrl,
                'status_msg' => $statusMsg,
                'response_body' => $response->body(),
            ]);

            $suffix = $statusMsg !== '' ? (' - '.$statusMsg) : '';
            if ($response->status() === 401) {
                $suffix .= ' - verifica server (TEST/LIVE), client_id, client_secret (se aplicavel) e bearer_token.';
            }
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
        $hydrateBody = $body;
        $hasReferenceInCreateResponse = trim((string) data_get($body, 'paymentReference.reference', '')) !== '';
        if ($code === 'sibs_multibanco' && ! $hasReferenceInCreateResponse && $transactionId !== '' && is_array($usedAttempt)) {
            $statusBody = $this->requestPaymentStatus(
                $apiUrl,
                $transactionId,
                $bearerToken,
                (string) ($usedAttempt['client_id'] ?? ''),
                (string) ($usedAttempt['client_secret'] ?? ''),
            );

            if (is_array($statusBody)) {
                $hydrateBody = array_replace_recursive($hydrateBody, $statusBody);
            }
        }

        $snapshot = $this->hydrateReferenceDetails($snapshot, $hydrateBody, (float) $order->total_inc_vat, (string) $order->currency);

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
                'message' => $code === 'sibs_mbway'
                    ? 'Pedido MB WAY iniciado. Confirma no telemovel.'
                    : 'Checkout SIBS iniciado. Continua o pagamento.',
                'redirect_url' => url('/loja/conta/encomendas/'.$order->id.'/pay/sibs'),
            ];
        }

        if ($code === 'sibs_multibanco') {
            return [
                'message' => 'Pedido Multibanco iniciado. Se a referencia nao aparecer ja, abre o checkout SIBS para concluir.',
                'redirect_url' => url('/loja/conta/encomendas/'.$order->id.'/pay/sibs'),
            ];
        }

        return [
            'message' => 'Pagamento SIBS iniciado.',
        ];
    }

    /**
     * @return array{message: string, updated: bool, has_reference: bool}
     */
    public function refreshMultibancoReference(Order $order): array
    {
        $snapshot = is_array($order->payment_method_snapshot) ? $order->payment_method_snapshot : [];
        $code = trim((string) ($snapshot['code'] ?? ''));
        if ($code !== 'sibs_multibanco') {
            throw new \RuntimeException('A atualizacao de referencia so esta disponivel para SIBS Referencia Multibanco.');
        }

        $transactionId = trim((string) data_get($snapshot, 'sibs_execution.transaction_id', ''));
        if ($transactionId === '') {
            throw new \RuntimeException('Nao existe transacao SIBS associada para atualizar a referencia.');
        }

        $meta = $this->resolveMeta($code, $snapshot);
        $serverMode = mb_strtoupper(trim((string) ($meta['server'] ?? data_get($snapshot, 'sibs_execution.server_mode', 'TEST'))), 'UTF-8');
        $apiUrl = $serverMode === 'LIVE' ? self::API_URL_LIVE : self::API_URL_TEST;
        $bearerToken = trim((string) ($meta['bearer_token'] ?? ''));
        $credentialAttempts = $this->buildCredentialAttempts($meta);

        if ($bearerToken === '' || $credentialAttempts === []) {
            throw new \RuntimeException('Credenciais SIBS incompletas para atualizar a referencia.');
        }

        $statusBody = null;
        foreach ($credentialAttempts as $attempt) {
            $statusBody = $this->requestPaymentStatus(
                $apiUrl,
                $transactionId,
                $bearerToken,
                $attempt['client_id'],
                $attempt['client_secret'],
            );

            if (is_array($statusBody)) {
                break;
            }
        }

        if (! is_array($statusBody)) {
            throw new \RuntimeException('Nao foi possivel consultar o estado da transacao SIBS.');
        }

        $snapshot['sibs_execution']['status_response'] = $statusBody;
        $snapshot = $this->hydrateReferenceDetails($snapshot, $statusBody, (float) $order->total_inc_vat, (string) $order->currency);

        $reference = trim((string) data_get($snapshot, 'payment_instructions.reference', ''));

        $order->payment_method_snapshot = $snapshot;
        $order->save();

        if ($reference !== '') {
            return [
                'message' => 'Referencia Multibanco atualizada com sucesso.',
                'updated' => true,
                'has_reference' => true,
            ];
        }

        return [
            'message' => 'A SIBS ainda nao devolveu a referencia para esta transacao.',
            'updated' => false,
            'has_reference' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function requestCheckout(
        string $apiUrl,
        array $payload,
        string $bearerToken,
        string $clientId,
        string $clientSecret = '',
    ) {
        $authToken = preg_replace('/^Bearer\s+/i', '', $bearerToken) ?: $bearerToken;

        $headers = [
            'Authorization' => 'Bearer '.$authToken,
            'Content-Type' => 'application/json;charset=UTF-8',
            'X-IBM-Client-id' => $clientId,
        ];

        if ($clientSecret !== '') {
            $headers['X-IBM-Client-Secret'] = $clientSecret;
        }

        return Http::timeout(30)
            ->withHeaders($headers)
            ->post($apiUrl, $payload);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function requestPaymentStatus(
        string $apiUrl,
        string $transactionId,
        string $bearerToken,
        string $clientId,
        string $clientSecret = '',
    ): ?array {
        if ($transactionId === '' || $clientId === '' || $bearerToken === '') {
            return null;
        }

        $authToken = preg_replace('/^Bearer\s+/i', '', $bearerToken) ?: $bearerToken;
        $headers = [
            'Authorization' => 'Bearer '.$authToken,
            'Content-Type' => 'application/json;charset=UTF-8',
            'X-IBM-Client-id' => $clientId,
        ];

        if ($clientSecret !== '') {
            $headers['X-IBM-Client-Secret'] = $clientSecret;
        }

        $statusUrl = rtrim($apiUrl, '/').'/'.$transactionId.'/status';
        $response = Http::timeout(30)
            ->withHeaders($headers)
            ->get($statusUrl);

        if (! $response->successful()) {
            Log::info('SIBS status lookup failed', [
                'transaction_id' => $transactionId,
                'status' => $response->status(),
                'url' => $statusUrl,
                'body' => $response->body(),
            ]);

            return null;
        }

        $body = $response->json();

        return is_array($body) ? $body : null;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<int, array{client_id: string, client_secret: string}>
     */
    private function buildCredentialAttempts(array $meta): array
    {
        $clientIds = array_values(array_filter(array_unique([
            trim((string) ($meta['client_id'] ?? '')),
            trim((string) ($meta['client_id_alt'] ?? env('SIBS_CLIENT_ID_ALT', ''))),
        ])));

        $secrets = array_values(array_unique([
            trim((string) ($meta['client_secret'] ?? '')),
            trim((string) ($meta['client_secret_alt'] ?? env('SIBS_CLIENT_SECRET_ALT', ''))),
            '',
        ]));

        $attempts = [];
        foreach ($clientIds as $clientId) {
            foreach ($secrets as $secret) {
                $attempts[] = [
                    'client_id' => $clientId,
                    'client_secret' => $secret,
                ];
            }
        }

        return $attempts;
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
            // Align with official SIBS PrestaShop module for MB reference creation.
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
        $channel = trim((string) ($meta['channel'] ?? 'web'));
        if ($channel === '') {
            $channel = 'web';
        }

        $amount = round((float) $order->total_inc_vat, 2);
        $currency = (string) $order->currency;
        $merchantTransactionId = (string) $order->order_number;

        $payload = [
            'merchant' => [
                'terminalId' => (int) $terminalId,
                'channel' => $channel,
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
                'transactionTimestamp' => now()->format('Y-m-d\TH:i:sP'),
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

        if ($paymentMethod === 'REFERENCE') {
            $entity = trim((string) ($meta['payment_entity'] ?? ''));
            if ($entity === '') {
                throw new \RuntimeException('Referencia Multibanco indisponivel: falta configurar a entidade MB na SIBS.');
            }

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
                'initialDatetime' => $initial->format('Y-m-d\TH:i:sP'),
                'finalDatetime' => $final->format('Y-m-d\TH:i:sP'),
                'maxAmount' => [
                    'value' => $amount,
                    'currency' => $currency,
                ],
                'minAmount' => [
                    'value' => $amount,
                    'currency' => $currency,
                ],
                'entity' => $entity,
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

        $existingInstructions = is_array($snapshot['payment_instructions'] ?? null) ? $snapshot['payment_instructions'] : [];
        $formContext = trim((string) data_get($snapshot, 'sibs_execution.form_context', ''));
        $formContextReference = $this->extractReferenceDetailsFromFormContext($formContext);

        $entity = trim((string) (
            data_get($body, 'paymentReference.entity')
            ?? $formContextReference['entity']
            ?? ($existingInstructions['entity'] ?? '')
        ));

        $reference = preg_replace('/\D+/', '', (string) (
            data_get($body, 'paymentReference.reference')
            ?? $formContextReference['reference']
            ?? ($existingInstructions['reference'] ?? '')
        ));
        $reference = is_string($reference) ? trim($reference) : '';
        $amount = (float) (
            data_get($body, 'paymentReference.amount.value')
            ?? $formContextReference['amount']
            ?? ($existingInstructions['amount'] ?? $defaultAmount)
        );
        $currency = trim((string) (
            data_get($body, 'paymentReference.amount.currency')
            ?? $formContextReference['currency']
            ?? ($existingInstructions['currency'] ?? $defaultCurrency)
        ));
        $expireDate = trim((string) (
            data_get($body, 'paymentReference.expireDate')
            ?? $formContextReference['expire_date']
            ?? ($existingInstructions['expire_date'] ?? '')
        ));

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

    /**
     * @return array{entity: string, reference: string, amount: float|null, currency: string, expire_date: string}
     */
    private function extractReferenceDetailsFromFormContext(string $formContext): array
    {
        if ($formContext === '') {
            return [
                'entity' => '',
                'reference' => '',
                'amount' => null,
                'currency' => '',
                'expire_date' => '',
            ];
        }

        $decoded = base64_decode($formContext, true);
        if (! is_string($decoded) || $decoded === '') {
            return [
                'entity' => '',
                'reference' => '',
                'amount' => null,
                'currency' => '',
                'expire_date' => '',
            ];
        }

        $json = json_decode($decoded, true);
        if (! is_array($json)) {
            return [
                'entity' => '',
                'reference' => '',
                'amount' => null,
                'currency' => '',
                'expire_date' => '',
            ];
        }

        $entity = trim((string) (
            data_get($json, 'PaymentReferenceEntities.0.Entity')
            ?? data_get($json, 'paymentReference.entity')
            ?? ''
        ));
        $reference = preg_replace('/\D+/', '', (string) (
            data_get($json, 'PaymentReferences.0.Reference')
            ?? data_get($json, 'PaymentReference.reference')
            ?? data_get($json, 'paymentReference.reference')
            ?? ''
        ));

        return [
            'entity' => $entity,
            'reference' => is_string($reference) ? trim($reference) : '',
            'amount' => is_numeric(data_get($json, 'paymentReference.amount.value')) ? (float) data_get($json, 'paymentReference.amount.value') : null,
            'currency' => trim((string) (data_get($json, 'paymentReference.amount.currency') ?? '')),
            'expire_date' => trim((string) (data_get($json, 'paymentReference.expireDate') ?? '')),
        ];
    }
}
