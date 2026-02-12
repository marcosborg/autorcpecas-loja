<?php

namespace App\Services\TpSoftware;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\Log;
use Throwable;

class TpSoftwareSalesSyncService
{
    public function __construct(
        private readonly TpSoftwareClient $client,
    ) {
    }

    /**
     * @return array{ok: bool, skipped?: bool, reason?: string, status?: int|null}
     */
    public function syncPaidOrder(Order $order): array
    {
        if (! (bool) config('tpsoftware.sales_sync.enabled', true)) {
            return ['ok' => true, 'skipped' => true, 'reason' => 'disabled'];
        }

        if ((string) config('storefront.catalog_provider', 'telepecas') !== 'tpsoftware') {
            return ['ok' => true, 'skipped' => true, 'reason' => 'provider_not_tpsoftware'];
        }

        if ((string) $order->status !== 'paid') {
            return ['ok' => true, 'skipped' => true, 'reason' => 'status_not_paid'];
        }

        $snapshot = is_array($order->payment_method_snapshot) ? $order->payment_method_snapshot : [];
        $existingStatus = (string) data_get($snapshot, 'tpsoftware_sale_sync.status', '');
        if ($existingStatus === 'success') {
            return ['ok' => true, 'skipped' => true, 'reason' => 'already_synced'];
        }

        $order->loadMissing('items', 'user');

        $products = $this->buildProducts($order);
        if ($products === []) {
            $this->markSync($order, [
                'status' => 'failed',
                'message' => 'Nenhum item com product_id TP valido para sincronizar.',
                'updated_at' => now()->toIso8601String(),
            ]);

            return ['ok' => false, 'reason' => 'no_valid_products'];
        }

        $payload = $this->buildSalesPayload($order, $products);

        try {
            $response = $this->client->post('ecommerce-generate-sales-order', $payload, 0, false);
        } catch (Throwable $e) {
            $this->markSync($order, [
                'status' => 'failed',
                'message' => $e->getMessage(),
                'updated_at' => now()->toIso8601String(),
            ]);

            Log::warning('TP Software sale sync exception', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'reason' => 'exception'];
        }

        if (! ($response['ok'] ?? false)) {
            $this->markSync($order, [
                'status' => 'failed',
                'http_status' => $response['status'] ?? null,
                'response' => $this->truncateForSnapshot($response['data'] ?? $response['raw'] ?? null),
                'updated_at' => now()->toIso8601String(),
            ]);

            Log::warning('TP Software sale sync failed', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'http_status' => $response['status'] ?? null,
                'response' => $response['data'] ?? $response['raw'] ?? null,
            ]);

            return [
                'ok' => false,
                'status' => $response['status'] ?? null,
                'reason' => 'http_error',
            ];
        }

        $this->markSync($order, [
            'status' => 'success',
            'http_status' => $response['status'] ?? null,
            'tp_sale_id' => data_get($response['data'], 'data.id')
                ?? data_get($response['data'], 'id')
                ?? data_get($response['data'], 'sale_id'),
            'updated_at' => now()->toIso8601String(),
        ]);

        Log::info('TP Software sale sync success', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'tp_sale_id' => data_get($response['data'], 'data.id')
                ?? data_get($response['data'], 'id')
                ?? data_get($response['data'], 'sale_id'),
        ]);

        return ['ok' => true, 'status' => $response['status'] ?? null];
    }

    /**
     * @param  list<array<string, mixed>>  $products
     * @return array<string, mixed>
     */
    private function buildSalesPayload(Order $order, array $products): array
    {
        $billing = is_array($order->billing_address_snapshot) ? $order->billing_address_snapshot : [];
        $shipping = is_array($order->shipping_address_snapshot) ? $order->shipping_address_snapshot : [];
        $vatRate = max(0.0, (float) $order->vat_rate);

        $subtotal = round((float) $order->subtotal_ex_vat, 2);
        $shippingExVat = round((float) $order->shipping_ex_vat, 2);
        $paymentFeeExVat = round((float) $order->payment_fee_ex_vat, 2);

        $taxProducts = round($subtotal * ($vatRate / 100), 2);
        $taxShipping = round($shippingExVat * ($vatRate / 100), 2);
        $taxOther = round($paymentFeeExVat * ($vatRate / 100), 2);

        $isVatExempt = $vatRate <= 0.0;

        $firstName = trim((string) ($billing['first_name'] ?? $shipping['first_name'] ?? ''));
        $lastName = trim((string) ($billing['last_name'] ?? $shipping['last_name'] ?? ''));
        $fullName = trim($firstName.' '.$lastName);
        $fullName = $fullName !== '' ? $fullName : 'Cliente';

        $country = strtoupper(trim((string) ($shipping['country_iso2'] ?? $billing['country_iso2'] ?? 'PT')));
        $city = trim((string) ($shipping['city'] ?? $billing['city'] ?? ''));
        $postalCode = trim((string) ($shipping['postal_code'] ?? $billing['postal_code'] ?? ''));
        $shippingAddress = trim((string) ($shipping['address_line1'] ?? ''));
        $invoiceAddress = trim((string) ($billing['address_line1'] ?? $shippingAddress));

        $phoneCountryCode = trim((string) ($billing['phone_country_code'] ?? $shipping['phone_country_code'] ?? '+351'));
        $phone = trim((string) ($billing['phone'] ?? $shipping['phone'] ?? ''));
        $phoneFull = trim($phoneCountryCode.' '.$phone);

        $vatNumber = strtoupper(trim((string) ($billing['vat_number'] ?? '')));
        $vatCountry = strtoupper(trim((string) ($billing['vat_country_iso2'] ?? $country)));
        $email = (string) ($order->user?->email ?? '');

        return [
            'ecommerce_id' => (string) $order->order_number,
            'num_products' => (int) $order->items->sum('quantity'),
            'total_value_products' => $subtotal,
            'total_value_shipping' => $shippingExVat,
            'total_value_packaging' => 0,
            'total_value_other_parcel' => $paymentFeeExVat,
            'tax_vat_products' => $taxProducts,
            'tax_vat_shipping' => $taxShipping,
            'tax_vat_packaging' => 0,
            'tax_vat_other_parcel' => $taxOther,
            'exemption_vat_products' => $isVatExempt ? $subtotal : 0,
            'exemption_vat_shipping' => $isVatExempt ? $shippingExVat : 0,
            'exemption_vat_packaging' => 0,
            'exemption_vat_other_parcel' => $isVatExempt ? $paymentFeeExVat : 0,
            'maximum_sale_alert_date' => now()->addDays(7)->toDateString(),
            'notes_sale' => trim((string) ($order->customer_note ?? '')),
            'seller_takes_care_shipping' => 1,
            'provider_takes_care_shipping' => 0,
            'seller_invoice_provider' => 0,
            'seller_invoice_client' => 1,
            'client' => [[
                'name' => $fullName,
                'country_code' => $country !== '' ? $country : 'PT',
                'shipping_address' => $shippingAddress,
                'postal_code' => $postalCode,
                'city' => $city,
                'invoice_address' => $invoiceAddress,
                'vat_number' => $vatNumber,
                'country_code_vat_number' => $vatCountry,
                'phone' => $phoneFull,
                'email' => $email,
                'whatsapp' => $phoneFull,
            ]],
            'provider' => [[
                'name' => (string) env('TPSOFTWARE_PROVIDER_NAME', config('app.name', 'Auto RC Pecas')),
                'country_code' => (string) env('TPSOFTWARE_PROVIDER_COUNTRY_CODE', 'PT'),
                'shipping_address' => (string) env('TPSOFTWARE_PROVIDER_SHIPPING_ADDRESS', 'Portugal'),
                'postal_code' => (string) env('TPSOFTWARE_PROVIDER_POSTAL_CODE', ''),
                'city' => (string) env('TPSOFTWARE_PROVIDER_CITY', ''),
                'invoice_address' => (string) env('TPSOFTWARE_PROVIDER_INVOICE_ADDRESS', ''),
                'vat_number' => (string) env('TPSOFTWARE_PROVIDER_VAT_NUMBER', ''),
                'country_code_vat_number' => (string) env('TPSOFTWARE_PROVIDER_COUNTRY_CODE', 'PT'),
                'phone' => (string) env('TPSOFTWARE_PROVIDER_PHONE', ''),
                'email' => (string) env('TPSOFTWARE_PROVIDER_EMAIL', ''),
                'whatsapp' => (string) env('TPSOFTWARE_PROVIDER_WHATSAPP', ''),
            ]],
            'products' => $products,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildProducts(Order $order): array
    {
        $products = [];
        $vatRate = max(0.0, (float) $order->vat_rate);

        /** @var OrderItem $item */
        foreach ($order->items as $item) {
            $quantity = max(1, (int) $item->quantity);
            $unitExVat = (float) $item->unit_price_ex_vat;
            if ($unitExVat <= 0 && $quantity > 0) {
                $unitExVat = round((float) $item->line_total_ex_vat / $quantity, 2);
            }

            $productId = $this->extractTpProductId($item);
            if ($productId === null) {
                continue;
            }

            $lineExVat = round($unitExVat * $quantity, 2);
            $taxVat = round($lineExVat * ($vatRate / 100), 2);

            $products[] = [
                'product_id' => $productId,
                'product_name' => (string) $item->title,
                'qty' => $quantity,
                'price_without_vat' => round($unitExVat, 2),
                'tax_vat' => $taxVat,
                'exemption_vat' => $vatRate <= 0.0 ? $lineExVat : 0,
                'images' => $this->extractProductImages($item),
            ];
        }

        return $products;
    }

    /**
     * @return int|string|null
     */
    private function extractTpProductId(OrderItem $item)
    {
        $payload = is_array($item->payload) ? $item->payload : [];

        $candidates = [
            data_get($payload, 'id'),
            data_get($payload, 'raw.id'),
            data_get($payload, 'raw.parts_id'),
            data_get($payload, 'raw.parts_internal_id'),
            data_get($payload, 'tp_id'),
            $item->product_key,
        ];

        foreach ($candidates as $candidate) {
            if (! is_scalar($candidate)) {
                continue;
            }

            $value = trim((string) $candidate);
            if ($value === '') {
                continue;
            }

            if (preg_match('/^\d+$/', $value) === 1) {
                return (int) $value;
            }

            return $value;
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function extractProductImages(OrderItem $item): array
    {
        $payload = is_array($item->payload) ? $item->payload : [];
        $images = [];

        foreach ((array) data_get($payload, 'raw.image_list', []) as $image) {
            if (! is_array($image)) {
                continue;
            }

            $url = trim((string) ($image['image_url'] ?? ''));
            if ($url !== '') {
                $images[] = $url;
            }
        }

        if ($images === []) {
            foreach ((array) data_get($payload, 'images', []) as $url) {
                if (! is_string($url)) {
                    continue;
                }

                $u = trim($url);
                if ($u !== '') {
                    $images[] = $u;
                }
            }
        }

        return array_values(array_unique($images));
    }

    /**
     * @param  array<string, mixed>  $syncData
     */
    private function markSync(Order $order, array $syncData): void
    {
        $snapshot = is_array($order->payment_method_snapshot) ? $order->payment_method_snapshot : [];
        $snapshot['tpsoftware_sale_sync'] = $syncData;

        Order::withoutEvents(function () use ($order, $snapshot): void {
            $order->forceFill([
                'payment_method_snapshot' => $snapshot,
            ])->save();
        });

        $order->payment_method_snapshot = $snapshot;
    }

    private function truncateForSnapshot(mixed $value): mixed
    {
        if (is_string($value)) {
            return mb_strlen($value, 'UTF-8') > 1200
                ? mb_substr($value, 0, 1200, 'UTF-8').'...[truncated]'
                : $value;
        }

        if (is_array($value)) {
            $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (is_string($json) && mb_strlen($json, 'UTF-8') > 1200) {
                return mb_substr($json, 0, 1200, 'UTF-8').'...[truncated]';
            }
        }

        return $value;
    }
}

