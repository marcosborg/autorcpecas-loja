<?php

namespace Tests\Unit;

use App\Models\PaymentMethod;
use App\Models\ShippingCarrier;
use App\Models\ShippingRate;
use App\Models\ShippingZone;
use App\Models\ShippingZoneCountry;
use App\Services\Checkout\CheckoutOptionsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutOptionsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_quote_returns_carriers_and_payment_methods_for_country(): void
    {
        $zone = ShippingZone::query()->create([
            'code' => 'PT',
            'name' => 'Portugal',
            'active' => true,
            'position' => 1,
        ]);

        ShippingZoneCountry::query()->create([
            'shipping_zone_id' => $zone->id,
            'country_iso2' => 'PT',
        ]);

        $carrier = ShippingCarrier::query()->create([
            'code' => 'ctt',
            'name' => 'CTT',
            'rate_basis' => 'price',
            'active' => true,
            'position' => 1,
        ]);

        ShippingRate::query()->create([
            'shipping_carrier_id' => $carrier->id,
            'shipping_zone_id' => $zone->id,
            'calc_type' => 'price',
            'range_from' => 0,
            'range_to' => null,
            'price_ex_vat' => 5.90,
            'handling_fee_ex_vat' => 0,
            'active' => true,
        ]);

        $method = PaymentMethod::query()->create([
            'code' => 'mbway',
            'name' => 'MB WAY',
            'fee_type' => 'none',
            'fee_value' => 0,
            'active' => true,
            'position' => 1,
        ]);
        $method->carriers()->attach($carrier->id);

        $service = app(CheckoutOptionsService::class);
        $quote = $service->quote(120.00, 2.0, 'PT');

        $this->assertSame('Portugal', $quote['zone']['name']);
        $this->assertCount(1, $quote['carriers']);
        $this->assertSame('CTT', $quote['carriers'][0]['name']);
        $this->assertSame(5.90, (float) $quote['carriers'][0]['price_ex_vat']);
        $this->assertCount(1, $quote['payment_methods']);
        $this->assertSame('MB WAY', $quote['payment_methods'][0]['name']);
    }

    public function test_quote_prefers_pt_zone_code_when_provided(): void
    {
        $mainland = ShippingZone::query()->create([
            'code' => 'PT_MAINLAND',
            'name' => 'Portugal Continental',
            'active' => true,
            'position' => 1,
        ]);
        $islands = ShippingZone::query()->create([
            'code' => 'PT_ISLANDS',
            'name' => 'Acores e Madeira',
            'active' => true,
            'position' => 2,
        ]);

        ShippingZoneCountry::query()->create([
            'shipping_zone_id' => $mainland->id,
            'country_iso2' => 'PT',
        ]);

        $carrier = ShippingCarrier::query()->create([
            'code' => 'ctt',
            'name' => 'CTT',
            'rate_basis' => 'price',
            'active' => true,
            'position' => 1,
        ]);

        ShippingRate::query()->create([
            'shipping_carrier_id' => $carrier->id,
            'shipping_zone_id' => $mainland->id,
            'calc_type' => 'price',
            'range_from' => 0,
            'range_to' => null,
            'price_ex_vat' => 5.90,
            'handling_fee_ex_vat' => 0,
            'active' => true,
        ]);
        ShippingRate::query()->create([
            'shipping_carrier_id' => $carrier->id,
            'shipping_zone_id' => $islands->id,
            'calc_type' => 'price',
            'range_from' => 0,
            'range_to' => null,
            'price_ex_vat' => 12.50,
            'handling_fee_ex_vat' => 0,
            'active' => true,
        ]);

        $method = PaymentMethod::query()->create([
            'code' => 'bank_transfer',
            'name' => 'Transferencia',
            'fee_type' => 'none',
            'fee_value' => 0,
            'active' => true,
            'position' => 1,
        ]);
        $method->carriers()->attach($carrier->id);

        $service = app(CheckoutOptionsService::class);
        $quote = $service->quote(120.00, 2.0, 'PT', 'PT_ISLANDS', '9500-100');

        $this->assertSame('Acores e Madeira', $quote['zone']['name']);
        $this->assertSame(12.50, (float) $quote['carriers'][0]['price_ex_vat']);
    }

    public function test_quote_does_not_reuse_last_rate_when_weight_is_out_of_range(): void
    {
        $zone = ShippingZone::query()->create(['code' => 'PS_ZONE_9', 'name' => 'Continente', 'active' => true, 'position' => 1]);
        $carrier = ShippingCarrier::query()->create([
            'code' => 'gls', 'name' => 'GLS', 'rate_basis' => 'weight', 'active' => true,
            'position' => 1, 'range_behavior' => 0,
        ]);
        ShippingRate::query()->create([
            'shipping_carrier_id' => $carrier->id, 'shipping_zone_id' => $zone->id,
            'calc_type' => 'weight', 'range_from' => 0, 'range_to' => 120,
            'price_ex_vat' => 50, 'handling_fee_ex_vat' => 0, 'active' => true,
        ]);

        $quote = app(CheckoutOptionsService::class)->quote(500, 150, 'PT', null, '2700-100');

        $this->assertSame([], $quote['carriers']);
        $this->assertSame('out_of_range', $quote['unavailable_reason']);
    }

    public function test_quote_requires_consultation_when_weight_is_unknown(): void
    {
        $zone = ShippingZone::query()->create(['code' => 'PS_ZONE_9', 'name' => 'Continente', 'active' => true, 'position' => 1]);

        $quote = app(CheckoutOptionsService::class)->quote(100, 0, 'PT', null, '2700-100', false);

        $this->assertSame([], $quote['carriers']);
        $this->assertSame('missing_weight', $quote['unavailable_reason']);
    }

    public function test_portuguese_postcodes_resolve_mainland_madeira_and_azores_zones(): void
    {
        foreach ([
            'PS_ZONE_9' => 'Continente',
            'PS_ZONE_19' => 'Madeira',
            'PS_ZONE_16' => 'Açores',
        ] as $code => $name) {
            ShippingZone::query()->create(['code' => $code, 'name' => $name, 'active' => true, 'position' => 1]);
        }

        $service = app(CheckoutOptionsService::class);

        $this->assertSame('PS_ZONE_9', $service->quote(10, 1, 'PT', null, '2700-100')['zone']['code']);
        $this->assertSame('PS_ZONE_19', $service->quote(10, 1, 'PT', 'PT_ISLANDS', '9000-100')['zone']['code']);
        $this->assertSame('PS_ZONE_16', $service->quote(10, 1, 'PT', 'PT_ISLANDS', '9500-100')['zone']['code']);
    }
}
