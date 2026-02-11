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
        'order_number' => 'ORC-WEBHOOK-0001',
        'payment_status' => 'paid',
        'transaction_id' => 'TX123',
        'webhook_secret' => 'secret-123',
    ]);

    $response
        ->assertOk()
        ->assertJson(['ok' => true]);

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
        'order_number' => 'ORC-WEBHOOK-0002',
        'payment_status' => 'paid',
        'webhook_secret' => 'wrong-secret',
    ]);

    $response->assertStatus(401)->assertJson(['ok' => false]);
    expect($order->fresh()->status)->toBe('awaiting_payment');
    Mail::assertNothingSent();
});

