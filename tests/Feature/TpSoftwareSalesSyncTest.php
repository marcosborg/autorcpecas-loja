<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

test('syncs paid order to tp software sales endpoint', function () {
    config()->set('storefront.catalog_provider', 'tpsoftware');
    config()->set('tpsoftware.sales_sync.enabled', true);
    config()->set('tpsoftware.base_url', 'https://api.tp.software/api/v1');
    config()->set('tpsoftware.token', 'tp-token-test');
    config()->set('tpsoftware.token_param', 'tokens');
    config()->set('tpsoftware.use_auth_header', false);

    Http::fake([
        'https://api.tp.software/api/v1/ecommerce-generate-sales-order*' => Http::response([
            'success' => true,
            'data' => [
                'id' => 98765,
            ],
        ], 200),
    ]);

    $user = User::factory()->create([
        'email' => 'tp-sync@example.com',
    ]);

    $order = Order::query()->create([
        'user_id' => $user->id,
        'order_number' => 'ORC-TPSYNC-0001',
        'status' => 'awaiting_payment',
        'currency' => 'EUR',
        'vat_rate' => 23,
        'subtotal_ex_vat' => 40,
        'shipping_ex_vat' => 10,
        'payment_fee_ex_vat' => 0,
        'total_ex_vat' => 50,
        'total_inc_vat' => 61.5,
        'shipping_address_snapshot' => [
            'first_name' => 'Joao',
            'last_name' => 'Cliente',
            'address_line1' => 'Rua Exemplo 1',
            'postal_code' => '4000-000',
            'city' => 'Porto',
            'country_iso2' => 'PT',
            'phone_country_code' => '+351',
            'phone' => '912345678',
        ],
        'billing_address_snapshot' => [
            'first_name' => 'Joao',
            'last_name' => 'Cliente',
            'address_line1' => 'Rua Exemplo 1',
            'postal_code' => '4000-000',
            'city' => 'Porto',
            'country_iso2' => 'PT',
            'phone_country_code' => '+351',
            'phone' => '912345678',
            'vat_number' => 'PT123456789',
            'vat_country_iso2' => 'PT',
        ],
        'shipping_method_snapshot' => ['name' => 'DPD'],
        'payment_method_snapshot' => ['code' => 'sibs_card', 'name' => 'SIBS Cartao'],
        'placed_at' => now(),
    ]);

    OrderItem::query()->create([
        'order_id' => $order->id,
        'product_key' => '2304',
        'reference' => 'E30176',
        'title' => 'Interruptor',
        'quantity' => 1,
        'unit_price_ex_vat' => 40,
        'line_total_ex_vat' => 40,
        'weight_kg' => 1,
        'payload' => [
            'id' => 2304,
            'raw' => [
                'id' => 2304,
                'parts_internal_id' => '2304',
                'image_list' => [
                    ['image_url' => 'https://example.com/p1.jpg'],
                ],
            ],
        ],
    ]);

    $order->status = 'paid';
    $order->save();

    Http::assertSent(function (Request $request): bool {
        $body = $request->data();

        return $request->method() === 'POST'
            && str_contains($request->url(), 'ecommerce-generate-sales-order')
            && str_contains($request->url(), 'tokens=tp-token-test')
            && (string) ($body['ecommerce_id'] ?? '') === 'ORC-TPSYNC-0001'
            && (int) data_get($body, 'products.0.product_id', 0) === 2304;
    });

    $order->refresh();
    expect((string) data_get($order->payment_method_snapshot, 'tpsoftware_sale_sync.status'))->toBe('success')
        ->and((int) data_get($order->payment_method_snapshot, 'tpsoftware_sale_sync.tp_sale_id'))->toBe(98765);
});

