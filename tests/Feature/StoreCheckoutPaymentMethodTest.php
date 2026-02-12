<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\PaymentMethod;
use App\Models\ShippingCarrier;
use App\Models\ShippingRate;
use App\Models\ShippingZone;
use App\Models\ShippingZoneCountry;
use App\Models\User;
use App\Mail\OrderLifecycleMail;
use App\Models\CustomerAddress;
use App\Services\Store\StoreCheckoutService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

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

test('executes multibanco payment flow for unpaid order', function () {
    Mail::fake();
    Http::fake([
        'https://spg.qly.site1.sibs.pt/api/v2/payments' => Http::response([
            'transactionID' => 'tx-mb-123',
            'returnStatus' => ['statusCode' => '000', 'statusMsg' => 'Success'],
            'paymentReference' => [
                'entity' => '12345',
                'reference' => '123456789',
                'amount' => ['value' => '30.75', 'currency' => 'EUR'],
                'expireDate' => '2030-01-01T00:00:00',
            ],
        ], 200),
    ]);

    $user = User::factory()->create([
        'email' => 'cliente-mb@example.com',
    ]);

    PaymentMethod::query()->create([
        'code' => 'sibs_multibanco',
        'name' => 'Referencia Multibanco',
        'provider' => 'SIBS',
        'fee_type' => 'fixed',
        'fee_value' => 0,
        'active' => true,
        'position' => 1,
        'meta' => [
            'gateway' => 'sibs',
            'client_id' => 'client-test',
            'terminal_id' => '1510829',
            'bearer_token' => 'token-test',
            'server' => 'TEST',
            'payment_entity' => '12345',
            'payment_type' => 'day',
            'payment_value' => '1',
        ],
    ]);

    $order = Order::query()->create([
        'user_id' => $user->id,
        'order_number' => 'ORC-20260212-000888',
        'status' => 'awaiting_payment',
        'currency' => 'EUR',
        'vat_rate' => 23,
        'subtotal_ex_vat' => 20,
        'shipping_ex_vat' => 5,
        'payment_fee_ex_vat' => 0,
        'total_ex_vat' => 25,
        'total_inc_vat' => 30.75,
        'shipping_address_snapshot' => ['country_iso2' => 'PT'],
        'billing_address_snapshot' => ['country_iso2' => 'PT'],
        'shipping_method_snapshot' => ['name' => 'DPD'],
        'payment_method_snapshot' => [
            'code' => 'sibs_multibanco',
            'name' => 'Referencia Multibanco',
            'meta' => ['gateway' => 'sibs', 'payment_entity' => '12345', 'payment_type' => 'PAGAMENTO'],
        ],
        'placed_at' => now(),
    ]);

    $result = app(StoreCheckoutService::class)->executeOrderPayment($order, (int) $user->id);

    $order->refresh();

    expect($result['email_sent'])->toBeTrue()
        ->and((string) data_get($order->payment_method_snapshot, 'payment_instructions.reference'))->toBe('123456789')
        ->and(OrderStatusHistory::query()->where('order_id', $order->id)->exists())->toBeTrue();

    Mail::assertSent(OrderLifecycleMail::class);
});

test('executes mbway payment flow and returns sibs redirect url', function () {
    Http::fake([
        'https://spg.qly.site1.sibs.pt/api/v2/payments' => Http::response([
            'transactionID' => 'tx-mbway-123',
            'returnStatus' => ['statusCode' => '000', 'statusMsg' => 'Success'],
            'formContext' => 'context-xyz',
            'transactionSignature' => 'signature-xyz',
        ], 200),
    ]);

    $user = User::factory()->create([
        'email' => 'cliente-mbway@example.com',
    ]);

    PaymentMethod::query()->create([
        'code' => 'sibs_mbway',
        'name' => 'SIBS MB WAY',
        'provider' => 'SIBS',
        'fee_type' => 'fixed',
        'fee_value' => 0,
        'active' => true,
        'position' => 1,
        'meta' => [
            'gateway' => 'sibs',
            'client_id' => 'client-test',
            'terminal_id' => '1510829',
            'bearer_token' => 'token-test',
            'server' => 'TEST',
            'mode' => 'DB',
        ],
    ]);

    $order = Order::query()->create([
        'user_id' => $user->id,
        'order_number' => 'ORC-20260212-000889',
        'status' => 'awaiting_payment',
        'currency' => 'EUR',
        'vat_rate' => 23,
        'subtotal_ex_vat' => 20,
        'shipping_ex_vat' => 5,
        'payment_fee_ex_vat' => 0,
        'total_ex_vat' => 25,
        'total_inc_vat' => 30.75,
        'shipping_address_snapshot' => ['country_iso2' => 'PT', 'first_name' => 'Ana', 'last_name' => 'Silva', 'phone' => '919111222'],
        'billing_address_snapshot' => ['country_iso2' => 'PT'],
        'shipping_method_snapshot' => ['name' => 'DPD'],
        'payment_method_snapshot' => [
            'code' => 'sibs_mbway',
            'name' => 'SIBS MB WAY',
            'meta' => ['gateway' => 'sibs'],
        ],
        'placed_at' => now(),
    ]);

    $result = app(StoreCheckoutService::class)->executeOrderPayment($order, (int) $user->id);
    $order->refresh();

    expect($result['email_sent'])->toBeFalse()
        ->and((string) ($result['redirect_url'] ?? ''))->toContain('/loja/conta/encomendas/'.$order->id.'/pay/sibs')
        ->and((string) data_get($order->payment_method_snapshot, 'sibs_execution.transaction_id'))->toBe('tx-mbway-123');
});

test('syncs order addresses from current customer defaults when retrying payment', function () {
    $user = User::factory()->create();

    CustomerAddress::query()->create([
        'user_id' => $user->id,
        'label' => 'Casa',
        'first_name' => 'Marco',
        'last_name' => 'Silva',
        'phone' => '919000111',
        'address_line1' => 'Rua Nova 10',
        'postal_code' => '3880-123',
        'city' => 'Ovar',
        'country_iso2' => 'PT',
        'is_default_shipping' => true,
        'is_default_billing' => true,
    ]);

    $order = Order::query()->create([
        'user_id' => $user->id,
        'order_number' => 'ORC-20260212-000890',
        'status' => 'awaiting_payment',
        'currency' => 'EUR',
        'vat_rate' => 23,
        'subtotal_ex_vat' => 20,
        'shipping_ex_vat' => 5,
        'payment_fee_ex_vat' => 0,
        'total_ex_vat' => 25,
        'total_inc_vat' => 30.75,
        'shipping_address_snapshot' => [
            'first_name' => 'Lexie',
            'last_name' => 'Fritsch',
            'address_line1' => '441 Weber Cliffs',
            'postal_code' => '7445',
            'city' => 'Paterson',
            'country_iso2' => 'PT',
        ],
        'billing_address_snapshot' => [
            'first_name' => 'Lexie',
            'last_name' => 'Fritsch',
            'address_line1' => '441 Weber Cliffs',
            'postal_code' => '7445',
            'city' => 'Paterson',
            'country_iso2' => 'PT',
        ],
        'shipping_method_snapshot' => ['name' => 'DPD'],
        'payment_method_snapshot' => [
            'code' => 'bank_transfer',
            'name' => 'Transferencia Bancaria',
            'meta' => ['gateway' => 'manual_bank_transfer'],
        ],
        'placed_at' => now(),
    ]);

    $this->actingAs($user)
        ->post('/loja/conta/encomendas/'.$order->id.'/pay')
        ->assertRedirect();

    $order->refresh();

    expect((string) data_get($order->shipping_address_snapshot, 'first_name'))->toBe('Marco')
        ->and((string) data_get($order->shipping_address_snapshot, 'address_line1'))->toBe('Rua Nova 10')
        ->and(
            OrderStatusHistory::query()
                ->where('order_id', $order->id)
                ->where('note', 'like', 'Moradas da encomenda sincronizadas%')
                ->exists()
        )->toBeTrue();
});
