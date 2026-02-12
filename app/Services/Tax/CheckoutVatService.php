<?php

namespace App\Services\Tax;

use App\Models\Country;
use App\Models\CustomerAddress;

class CheckoutVatService
{
    public function resolveVatRate(CustomerAddress $shippingAddress, CustomerAddress $billingAddress): float
    {
        return $this->resolveVatRateFromSnapshot(
            $shippingAddress->snapshot(),
            $billingAddress->snapshot(),
        );
    }

    /**
     * @param  array<string, mixed>  $shippingSnapshot
     * @param  array<string, mixed>  $billingSnapshot
     */
    public function resolveVatRateFromSnapshot(array $shippingSnapshot, array $billingSnapshot): float
    {
        $shippingIso2 = mb_strtoupper(trim((string) ($shippingSnapshot['country_iso2'] ?? 'PT')), 'UTF-8');
        $billingIso2 = mb_strtoupper(trim((string) ($billingSnapshot['country_iso2'] ?? $shippingIso2)), 'UTF-8');

        if ($shippingIso2 === 'PT') {
            return 23.0;
        }

        $shippingCountry = Country::query()->where('iso2', $shippingIso2)->first();
        $isShippingInEu = (bool) ($shippingCountry?->is_eu ?? false);
        if (! $isShippingInEu) {
            return 0.0;
        }

        $vatIsValid = (bool) ($billingSnapshot['vat_is_valid'] ?? false);
        $vatCountry = mb_strtoupper(trim((string) ($billingSnapshot['vat_country_iso2'] ?? '')), 'UTF-8');
        if ($vatIsValid && $vatCountry !== '' && $vatCountry === $billingIso2 && $billingIso2 !== 'PT') {
            return 0.0;
        }

        return 23.0;
    }
}
