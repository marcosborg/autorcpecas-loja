<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentMethod;
use App\Models\ShippingCarrier;
use App\Models\ShippingRate;
use App\Models\ShippingZone;
use App\Models\ShippingZoneCountry;
use App\Models\User;
use App\Services\Store\StoreCheckoutService;

test('allows changing payment method for unpaid order and stores multibanco instructions', function () {
    $user = User::factory()->create();

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
        'code' => 'dpd',
        'name' => 'DPD',
        'rate_basis' => 'price',
        'transit_delay' => '24-48h',
        'free_shipping_over_ex_vat' => null,
        'is_pickup' => false,
        'need_range' => true,
        'range_behavior' => 0,
        'is_free' => false,
        'active' => true,
        'position' => 1,
    ]);

    ShippingRate::query()->create([
        'shipping_carrier_id' => $carrier->id,
        'shipping_zone_id' => $zone->id,
        'calc_type' => 'price',
        'range_from' => 0,
        'range_to' => 999999,
        'price_ex_vat' => 5,
        'handling_fee_ex_vat' => 0,
        'active' => true,
    ]);

    $transfer = PaymentMethod::query()->create([
        'code' => 'bank_transfer',
        'name' => 'Transferencia',
        'provider' => 'manual',
        'fee_type' => 'fixed',
        'fee_value' => 0,
        'active' => true,
        'position' => 1,
        'meta' => ['gateway' => 'manual_bank_transfer'],
    ]);

    $multibanco = PaymentMethod::query()->create([
        'code' => 'sibs_multibanco',
        'name' => 'Referencia Multibanco',
        'provider' => 'sibs',
        'fee_type' => 'fixed',
        'fee_value' => 2,
        'active' => true,
        'position' => 2,
        'meta' => [
            'gateway' => 'sibs',
            'method' => 'multibanco',
            'payment_entity' => '12345',
            'payment_type' => 'PAGAMENTO',
            'client_id' => 'client',
            'terminal_id' => 'terminal',
            'bearer_token' => 'token',
        ],
    ]);

    $order = Order::query()->create([
        'user_id' => $user->id,
        'order_number' => 'ORC-20260211-000777',
        'status' => 'awaiting_payment',
        'currency' => 'EUR',
        'vat_rate' => 23,
        'subtotal_ex_vat' => 10,
        'shipping_ex_vat' => 5,
        'payment_fee_ex_vat' => 0,
        'total_ex_vat' => 15,
        'total_inc_vat' => 18.45,
        'shipping_address_snapshot' => ['country_iso2' => 'PT'],
        'billing_address_snapshot' => ['country_iso2' => 'PT'],
        'shipping_method_snapshot' => ['id' => $carrier->id, 'name' => 'DPD'],
        'payment_method_snapshot' => ['id' => $transfer->id, 'code' => 'bank_transfer', 'name' => 'Transferencia'],
        'placed_at' => now(),
    ]);

    OrderItem::query()->create([
        'order_id' => $order->id,
        'product_key' => 'p1',
        'reference' => 'R1',
        'title' => 'Produto 1',
        'quantity' => 1,
        'unit_price_ex_vat' => 10,
        'line_total_ex_vat' => 10,
        'weight_kg' => 1,
        'payload' => [],
    ]);

    $updated = app(StoreCheckoutService::class)->changeOrderPaymentMethod(
        $order->fresh(['items']),
        (int) $multibanco->id,
        (int) $user->id,
    );

    expect($updated->payment_fee_ex_vat)->toBe(2.0)
        ->and($updated->total_ex_vat)->toBe(17.0)
        ->and($updated->total_inc_vat)->toBe(20.91)
        ->and(data_get($updated->payment_method_snapshot, 'code'))->toBe('sibs_multibanco')
        ->and((string) data_get($updated->payment_method_snapshot, 'payment_instructions.entity'))->toBe('12345')
        ->and((string) data_get($updated->payment_method_snapshot, 'payment_instructions.reference'))->toHaveLength(9);
});
