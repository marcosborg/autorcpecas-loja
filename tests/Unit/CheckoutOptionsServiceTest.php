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
}
