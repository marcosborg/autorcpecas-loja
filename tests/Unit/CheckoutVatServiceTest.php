<?php

namespace Tests\Unit;

use App\Models\Country;
use App\Services\Tax\CheckoutVatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutVatServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_domestic_portugal_keeps_23_percent_vat(): void
    {
        Country::query()->create([
            'iso2' => 'PT',
            'name' => 'Portugal',
            'phone_code' => '+351',
            'is_eu' => true,
            'active' => true,
            'position' => 1,
        ]);

        $service = app(CheckoutVatService::class);
        $rate = $service->resolveVatRateFromSnapshot(
            ['country_iso2' => 'PT'],
            ['country_iso2' => 'PT', 'vat_is_valid' => true, 'vat_country_iso2' => 'PT']
        );

        $this->assertSame(23.0, $rate);
    }

    public function test_intra_eu_with_valid_vat_applies_zero_rate(): void
    {
        Country::query()->create([
            'iso2' => 'PT',
            'name' => 'Portugal',
            'phone_code' => '+351',
            'is_eu' => true,
            'active' => true,
            'position' => 1,
        ]);
        Country::query()->create([
            'iso2' => 'ES',
            'name' => 'Espanha',
            'phone_code' => '+34',
            'is_eu' => true,
            'active' => true,
            'position' => 2,
        ]);

        $service = app(CheckoutVatService::class);
        $rate = $service->resolveVatRateFromSnapshot(
            ['country_iso2' => 'ES'],
            ['country_iso2' => 'ES', 'vat_is_valid' => true, 'vat_country_iso2' => 'ES']
        );

        $this->assertSame(0.0, $rate);
    }

    public function test_extra_eu_applies_zero_rate(): void
    {
        Country::query()->create([
            'iso2' => 'GB',
            'name' => 'Reino Unido',
            'phone_code' => '+44',
            'is_eu' => false,
            'active' => true,
            'position' => 1,
        ]);

        $service = app(CheckoutVatService::class);
        $rate = $service->resolveVatRateFromSnapshot(
            ['country_iso2' => 'GB'],
            ['country_iso2' => 'GB']
        );

        $this->assertSame(0.0, $rate);
    }
}
