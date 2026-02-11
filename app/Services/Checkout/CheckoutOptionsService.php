<?php

namespace App\Services\Checkout;

use App\Models\PaymentMethod;
use App\Models\ShippingCarrier;
use App\Models\ShippingZone;
use App\Models\ShippingZoneCountry;

class CheckoutOptionsService
{
    /**
     * @return array{
     *   zone: array<string, mixed>|null,
     *   carriers: list<array<string, mixed>>,
     *   payment_methods: list<array<string, mixed>>
     * }
     */
    public function quote(float $subtotalExVat, float $weightKg, string $countryIso2 = 'PT'): array
    {
        $countryIso2 = mb_strtoupper(trim($countryIso2), 'UTF-8');
        $zone = $this->resolveZone($countryIso2);
        $zoneId = $zone?->id;

        if (! $zoneId) {
            return [
                'zone' => null,
                'carriers' => [],
                'payment_methods' => [],
            ];
        }

        $carrierQuotes = $this->carrierQuotes((float) $subtotalExVat, (float) $weightKg, (int) $zoneId);
        $carrierIds = array_map(fn (array $c): int => (int) $c['id'], $carrierQuotes);
        $paymentMethods = $this->paymentMethods((float) $subtotalExVat, $carrierIds);

        return [
            'zone' => [
                'id' => $zone->id,
                'code' => $zone->code,
                'name' => $zone->name,
            ],
            'carriers' => $carrierQuotes,
            'payment_methods' => $paymentMethods,
        ];
    }

    private function resolveZone(string $countryIso2): ?ShippingZone
    {
        $zoneId = ShippingZoneCountry::query()
            ->whereRaw('UPPER(country_iso2) = ?', [$countryIso2])
            ->value('shipping_zone_id');

        if ($zoneId) {
            return ShippingZone::query()->where('active', true)->find($zoneId);
        }

        return ShippingZone::query()
            ->where('active', true)
            ->orderBy('position')
            ->orderBy('id')
            ->first();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function carrierQuotes(float $subtotalExVat, float $weightKg, int $zoneId): array
    {
        $carriers = ShippingCarrier::query()
            ->where('active', true)
            ->orderBy('position')
            ->orderBy('id')
            ->with(['rates' => function ($q) use ($zoneId): void {
                $q->where('active', true)
                    ->where('shipping_zone_id', $zoneId)
                    ->orderBy('range_from')
                    ->orderBy('id');
            }])
            ->get();

        $out = [];

        foreach ($carriers as $carrier) {
            $basis = $carrier->rate_basis === 'weight' ? 'weight' : 'price';
            $subject = $basis === 'weight' ? $weightKg : $subtotalExVat;

            $rate = $carrier->rates
                ->first(function ($r) use ($basis, $subject): bool {
                    if (($r->calc_type ?? 'price') !== $basis) {
                        return false;
                    }
                    if ($subject < (float) $r->range_from) {
                        return false;
                    }

                    $to = $r->range_to;

                    return $to === null || $subject <= (float) $to;
                });

            if (! $rate && (bool) $carrier->is_free && ! (bool) $carrier->need_range) {
                $out[] = [
                    'id' => $carrier->id,
                    'code' => $carrier->code,
                    'name' => $carrier->name,
                    'delay' => $carrier->transit_delay,
                    'is_pickup' => (bool) $carrier->is_pickup,
                    'price_ex_vat' => 0.0,
                    'basis' => $basis,
                ];
                continue;
            }

            if (! $rate && (int) $carrier->range_behavior === 0) {
                $rate = $carrier->rates
                    ->where('calc_type', $basis)
                    ->sortBy('range_to')
                    ->last();
            }

            if (! $rate) {
                continue;
            }

            $shipping = (float) $rate->price_ex_vat + (float) $rate->handling_fee_ex_vat;
            $freeOver = $carrier->free_shipping_over_ex_vat;
            if ($freeOver !== null && $freeOver >= 0 && $subtotalExVat >= (float) $freeOver) {
                $shipping = 0.0;
            }

            $out[] = [
                'id' => $carrier->id,
                'code' => $carrier->code,
                'name' => $carrier->name,
                'delay' => $carrier->transit_delay,
                'is_pickup' => (bool) $carrier->is_pickup,
                'price_ex_vat' => round($shipping, 2),
                'basis' => $basis,
            ];
        }

        return $out;
    }

    /**
     * @param  list<int>  $carrierIds
     * @return list<array<string, mixed>>
     */
    private function paymentMethods(float $subtotalExVat, array $carrierIds): array
    {
        $methods = PaymentMethod::query()
            ->where('active', true)
            ->orderBy('position')
            ->orderBy('id')
            ->with('carriers:id')
            ->get();

        $out = [];

        foreach ($methods as $method) {
            $attachedCarrierIds = $method->carriers->pluck('id')->map(fn ($v): int => (int) $v)->all();
            if (count($attachedCarrierIds) > 0 && count(array_intersect($attachedCarrierIds, $carrierIds)) === 0) {
                continue;
            }

            $fee = 0.0;
            if ($method->fee_type === 'fixed') {
                $fee = (float) $method->fee_value;
            } elseif ($method->fee_type === 'percent') {
                $fee = round($subtotalExVat * (((float) $method->fee_value) / 100), 2);
            }

            $out[] = [
                'id' => $method->id,
                'code' => $method->code,
                'name' => $method->name,
                'provider' => $method->provider,
                'fee_ex_vat' => round($fee, 2),
                'fee_type' => $method->fee_type,
                'meta' => $this->publicPaymentMeta(is_array($method->meta) ? $method->meta : []),
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function publicPaymentMeta(array $meta): array
    {
        $gateway = (string) ($meta['gateway'] ?? '');
        if ($gateway === 'sibs') {
            $clientId = trim((string) ($meta['client_id'] ?? ''));
            $terminalId = trim((string) ($meta['terminal_id'] ?? ''));
            $bearer = trim((string) ($meta['bearer_token'] ?? ''));

            return [
                'gateway' => 'sibs',
                'method' => (string) ($meta['method'] ?? ''),
                'server' => (string) ($meta['server'] ?? ''),
                'integration_ready' => $clientId !== '' && $terminalId !== '' && $bearer !== '',
                'payment_entity' => (string) ($meta['payment_entity'] ?? ''),
                'payment_type' => (string) ($meta['payment_type'] ?? ''),
            ];
        }

        if ($gateway === 'manual_bank_transfer') {
            return [
                'gateway' => 'manual_bank_transfer',
                'owner' => (string) ($meta['owner'] ?? ''),
                'details' => (string) ($meta['details'] ?? ''),
                'address' => (string) ($meta['address'] ?? ''),
            ];
        }

        return [];
    }
}
