<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use App\Models\ShippingCarrier;
use App\Models\ShippingRate;
use App\Models\ShippingZone;
use App\Models\ShippingZoneCountry;
use Illuminate\Database\Seeder;

class ShippingAndPaymentSeeder extends Seeder
{
    public function run(): void
    {
        $pt = ShippingZone::query()->updateOrCreate(
            ['code' => 'PT_MAINLAND'],
            ['name' => 'Portugal Continental', 'active' => true, 'position' => 1]
        );
        $islands = ShippingZone::query()->updateOrCreate(
            ['code' => 'PT_ISLANDS'],
            ['name' => 'Açores e Madeira', 'active' => true, 'position' => 2]
        );
        $eu = ShippingZone::query()->updateOrCreate(
            ['code' => 'EU'],
            ['name' => 'União Europeia', 'active' => true, 'position' => 3]
        );

        foreach (['PT'] as $iso) {
            ShippingZoneCountry::query()->updateOrCreate(['shipping_zone_id' => $pt->id, 'country_iso2' => $iso]);
        }
        foreach (['ES', 'FR', 'DE'] as $iso) {
            ShippingZoneCountry::query()->updateOrCreate(['shipping_zone_id' => $eu->id, 'country_iso2' => $iso]);
        }
        // fallback islands attached to PT until postcode logic exists
        ShippingZoneCountry::query()->updateOrCreate(['shipping_zone_id' => $islands->id, 'country_iso2' => 'PT']);

        $ctt = ShippingCarrier::query()->updateOrCreate(
            ['code' => 'ctt_standard'],
            ['name' => 'CTT Expresso', 'rate_basis' => 'weight', 'transit_delay' => '24h a 48h', 'active' => true, 'position' => 1]
        );
        $nacex = ShippingCarrier::query()->updateOrCreate(
            ['code' => 'nacex_urgent'],
            ['name' => 'NACEX Urgente', 'rate_basis' => 'weight', 'transit_delay' => 'Entrega no dia útil seguinte', 'active' => true, 'position' => 2]
        );
        $pickup = ShippingCarrier::query()->updateOrCreate(
            ['code' => 'store_pickup'],
            ['name' => 'Levantamento em loja', 'rate_basis' => 'price', 'transit_delay' => 'Mesmo dia (após confirmação)', 'is_pickup' => true, 'active' => true, 'position' => 3]
        );

        $this->seedRates($ctt->id, $pt->id, 'weight', [
            [0, 5, 4.90],
            [5, 20, 7.90],
            [20, null, 12.90],
        ]);
        $this->seedRates($ctt->id, $eu->id, 'weight', [
            [0, 5, 14.90],
            [5, 20, 24.90],
            [20, null, 39.90],
        ]);
        $this->seedRates($nacex->id, $pt->id, 'weight', [
            [0, 5, 7.90],
            [5, 20, 10.90],
            [20, null, 16.90],
        ]);
        $this->seedRates($pickup->id, $pt->id, 'price', [
            [0, null, 0.00],
        ]);

        $mbway = PaymentMethod::query()->updateOrCreate(
            ['code' => 'mbway'],
            ['name' => 'MB WAY', 'provider' => 'SIBS', 'fee_type' => 'none', 'fee_value' => 0, 'active' => true, 'position' => 1]
        );
        $multibanco = PaymentMethod::query()->updateOrCreate(
            ['code' => 'multibanco_ref'],
            ['name' => 'Referência Multibanco', 'provider' => 'SIBS', 'fee_type' => 'none', 'fee_value' => 0, 'active' => true, 'position' => 2]
        );
        $card = PaymentMethod::query()->updateOrCreate(
            ['code' => 'card'],
            ['name' => 'Cartão de crédito/débito', 'provider' => 'Stripe', 'fee_type' => 'percent', 'fee_value' => 1.50, 'active' => true, 'position' => 3]
        );
        $bank = PaymentMethod::query()->updateOrCreate(
            ['code' => 'bank_transfer'],
            ['name' => 'Transferência bancária', 'provider' => 'Manual', 'fee_type' => 'none', 'fee_value' => 0, 'active' => true, 'position' => 4]
        );

        // Example restrictions: MB WAY not available for pickup-only scenarios can be adjusted here.
        $mbway->carriers()->syncWithoutDetaching([$ctt->id, $nacex->id, $pickup->id]);
        $multibanco->carriers()->syncWithoutDetaching([$ctt->id, $nacex->id, $pickup->id]);
        $card->carriers()->syncWithoutDetaching([$ctt->id, $nacex->id, $pickup->id]);
        $bank->carriers()->syncWithoutDetaching([$ctt->id, $nacex->id, $pickup->id]);
    }

    /**
     * @param  list<array{0: float|int, 1: float|int|null, 2: float|int}>  $ranges
     */
    private function seedRates(int $carrierId, int $zoneId, string $calcType, array $ranges): void
    {
        foreach ($ranges as [$from, $to, $price]) {
            ShippingRate::query()->updateOrCreate(
                [
                    'shipping_carrier_id' => $carrierId,
                    'shipping_zone_id' => $zoneId,
                    'calc_type' => $calcType,
                    'range_from' => (float) $from,
                    'range_to' => $to === null ? null : (float) $to,
                ],
                [
                    'price_ex_vat' => (float) $price,
                    'handling_fee_ex_vat' => 0,
                    'active' => true,
                ]
            );
        }
    }
}

