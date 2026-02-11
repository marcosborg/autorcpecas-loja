<?php

use App\Mail\OrderLifecycleMail;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

test('sibs webhook confirms payment and sends payment email once', function () {
    Mail::fake();

    $user = User::factory()->create([
        'email' => 'cliente-webhook@example.com',
    ]);

    PaymentMethod::query()->create([
        'code' => 'sibs_multibanco',
        'name' => 'Multibanco',
        'provider' => 'SIBS',
        'fee_type' => 'none',
        'fee_value' => 0,
        'active' => true,
        'position' => 1,
        'meta' => [
            'gateway' => 'sibs',
            'webhook_secret' => 'secret-123',
        ],
    ]);

    $order = Order::query()->create([
        'user_id' => $user->id,
        'order_number' => 'ORC-WEBHOOK-0001',
        'status' => 'awaiting_payment',
        'currency' => 'EUR',
        'vat_rate' => 23,
        'subtotal_ex_vat' => 50,
        'shipping_ex_vat' => 10,
        'payment_fee_ex_vat' => 0,
        'total_ex_vat' => 60,
        'total_inc_vat' => 73.8,
        'shipping_address_snapshot' => ['first_name' => 'Cliente', 'last_name' => 'Webhook'],
        'billing_address_snapshot' => ['first_name' => 'Cliente', 'last_name' => 'Webhook'],
        'shipping_method_snapshot' => ['name' => 'DPD'],
        'payment_method_snapshot' => ['code' => 'sibs_multibanco', 'name' => 'Multibanco'],
        'placed_at' => now(),
    ]);

    $response = $this->postJson('/webhooks/sibs/payment', [
        'notificationID' => 'notif-1',
        'paymentType' => 'PURS',
        'order_number' => 'ORC-WEBHOOK-0001',
        'paymentStatus' => 'Success',
        'transactionID' => 'TX123',
        'webhook_secret' => 'secret-123',
    ]);

    $response
        ->assertOk()
        ->assertJson([
            'notificationID' => 'notif-1',
            'statusCode' => 200,
            'statusMsg' => 'Success',
        ]);

    expect($order->fresh()->status)->toBe('paid');

    $history = OrderStatusHistory::query()
        ->where('order_id', $order->id)
        ->latest('id')
        ->first();

    expect((string) ($history?->note ?? ''))->toContain('webhook SIBS');
    expect((string) ($history?->note ?? ''))->toContain('TX123');

    Mail::assertSent(OrderLifecycleMail::class, 1);
    Mail::assertSent(OrderLifecycleMail::class, function (OrderLifecycleMail $mail) use ($order): bool {
        return $mail->order->is($order)
            && ($mail->context['title'] ?? '') === 'Atualização de pagamento da encomenda';
    });
});

test('sibs webhook rejects invalid secret', function () {
    Mail::fake();

    $user = User::factory()->create([
        'email' => 'cliente-invalid@example.com',
    ]);

    PaymentMethod::query()->create([
        'code' => 'sibs_mbway',
        'name' => 'MBWay',
        'provider' => 'SIBS',
        'fee_type' => 'none',
        'fee_value' => 0,
        'active' => true,
        'position' => 2,
        'meta' => [
            'gateway' => 'sibs',
            'webhook_secret' => 'secret-correct',
        ],
    ]);

    $order = Order::query()->create([
        'user_id' => $user->id,
        'order_number' => 'ORC-WEBHOOK-0002',
        'status' => 'awaiting_payment',
        'currency' => 'EUR',
        'vat_rate' => 23,
        'subtotal_ex_vat' => 20,
        'shipping_ex_vat' => 5,
        'payment_fee_ex_vat' => 0,
        'total_ex_vat' => 25,
        'total_inc_vat' => 30.75,
        'shipping_address_snapshot' => ['first_name' => 'Cliente', 'last_name' => 'Invalid'],
        'billing_address_snapshot' => ['first_name' => 'Cliente', 'last_name' => 'Invalid'],
        'shipping_method_snapshot' => ['name' => 'DPD'],
        'payment_method_snapshot' => ['code' => 'sibs_mbway', 'name' => 'MBWay'],
        'placed_at' => now(),
    ]);

    $response = $this->postJson('/webhooks/sibs/payment', [
        'notificationID' => 'notif-2',
        'paymentType' => 'PURS',
        'order_number' => 'ORC-WEBHOOK-0002',
        'paymentStatus' => 'Success',
        'webhook_secret' => 'wrong-secret',
    ]);

    $response->assertStatus(401)->assertJson([
        'notificationID' => 'notif-2',
        'statusCode' => 401,
        'statusMsg' => 'Error',
    ]);
    expect($order->fresh()->status)->toBe('awaiting_payment');
    Mail::assertNothingSent();
});

test('sibs encrypted webhook is decrypted and updates order without x-webhook-secret header', function () {
    Mail::fake();

    $user = User::factory()->create([
        'email' => 'cliente-encrypted@example.com',
    ]);

    $secret = base64_encode(random_bytes(32));

    PaymentMethod::query()->create([
        'code' => 'sibs_card',
        'name' => 'SIBS Cartao',
        'provider' => 'SIBS',
        'fee_type' => 'none',
        'fee_value' => 0,
        'active' => true,
        'position' => 3,
        'meta' => [
            'gateway' => 'sibs',
            'webhook_secret' => $secret,
        ],
    ]);

    $order = Order::query()->create([
        'user_id' => $user->id,
        'order_number' => 'ORC-WEBHOOK-0003',
        'status' => 'awaiting_payment',
        'currency' => 'EUR',
        'vat_rate' => 23,
        'subtotal_ex_vat' => 120,
        'shipping_ex_vat' => 0,
        'payment_fee_ex_vat' => 0,
        'total_ex_vat' => 120,
        'total_inc_vat' => 147.6,
        'shipping_address_snapshot' => ['first_name' => 'Cliente', 'last_name' => 'Encrypt'],
        'billing_address_snapshot' => ['first_name' => 'Cliente', 'last_name' => 'Encrypt'],
        'shipping_method_snapshot' => ['name' => 'DPD'],
        'payment_method_snapshot' => ['code' => 'sibs_card', 'name' => 'SIBS Cartao'],
        'placed_at' => now(),
    ]);

    $payload = [
        'notificationID' => 'notif-enc-1',
        'transactionID' => 'TX-ENC-123',
        'paymentType' => 'PURS',
        'paymentStatus' => 'Success',
        'merchant' => [
            'merchantTransactionId' => 'ORC-WEBHOOK-0003',
        ],
    ];

    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt(
        json_encode($payload, JSON_THROW_ON_ERROR),
        'aes-256-gcm',
        base64_decode($secret, true),
        OPENSSL_RAW_DATA,
        $iv,
        $tag
    );

    expect($cipher)->not->toBeFalse();

    $response = $this->call(
        'POST',
        '/webhooks/sibs/payment',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'text/plain',
            'HTTP_X_INITIALIZATION_VECTOR' => base64_encode($iv),
            'HTTP_X_AUTHENTICATION_TAG' => base64_encode($tag),
        ],
        $cipher
    );

    $response->assertOk()->assertJson([
        'notificationID' => 'notif-enc-1',
        'statusCode' => 200,
        'statusMsg' => 'Success',
    ]);

    expect($order->fresh()->status)->toBe('paid');

    Mail::assertSent(OrderLifecycleMail::class, 1);
});
