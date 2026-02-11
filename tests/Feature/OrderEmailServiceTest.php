<?php

use App\Mail\OrderLifecycleMail;
use App\Models\Order;
use App\Models\User;
use App\Services\Orders\OrderEmailService;
use Illuminate\Support\Facades\Mail;

test('sends order created email with checkout context', function () {
    Mail::fake();

    $user = User::factory()->create([
        'name' => 'Cliente Checkout',
        'email' => 'checkout@example.com',
    ]);

    $order = Order::query()->create([
        'user_id' => $user->id,
        'order_number' => 'ORC-TEST-900001',
        'status' => 'awaiting_payment',
        'currency' => 'EUR',
        'vat_rate' => 23,
        'subtotal_ex_vat' => 40,
        'shipping_ex_vat' => 8,
        'payment_fee_ex_vat' => 0,
        'total_ex_vat' => 48,
        'total_inc_vat' => 59.04,
        'shipping_address_snapshot' => ['first_name' => 'Cliente', 'last_name' => 'Checkout'],
        'billing_address_snapshot' => ['first_name' => 'Cliente', 'last_name' => 'Checkout'],
        'shipping_method_snapshot' => ['name' => 'DPD'],
        'payment_method_snapshot' => ['name' => 'Transferencia Bancaria'],
        'placed_at' => now(),
    ]);

    app(OrderEmailService::class)->sendOrderCreated($order);

    Mail::assertSent(OrderLifecycleMail::class, function (OrderLifecycleMail $mail) use ($order): bool {
        return $mail->order->is($order)
            && ($mail->context['title'] ?? '') === 'Recebemos a tua encomenda';
    });
});

test('includes multibanco instructions in order created email', function () {
    Mail::fake();

    $user = User::factory()->create([
        'name' => 'Cliente MB',
        'email' => 'mb@example.com',
    ]);

    $order = Order::query()->create([
        'user_id' => $user->id,
        'order_number' => 'ORC-TEST-900002',
        'status' => 'awaiting_payment',
        'currency' => 'EUR',
        'vat_rate' => 23,
        'subtotal_ex_vat' => 50,
        'shipping_ex_vat' => 5,
        'payment_fee_ex_vat' => 0,
        'total_ex_vat' => 55,
        'total_inc_vat' => 67.65,
        'shipping_address_snapshot' => ['first_name' => 'Cliente', 'last_name' => 'MB'],
        'billing_address_snapshot' => ['first_name' => 'Cliente', 'last_name' => 'MB'],
        'shipping_method_snapshot' => ['name' => 'DPD'],
        'payment_method_snapshot' => [
            'code' => 'sibs_multibanco',
            'name' => 'Referencia Multibanco',
            'payment_instructions' => [
                'entity' => '12345',
                'reference' => '123456789',
                'reference_display' => '123 456 789',
                'amount' => 67.65,
                'currency' => 'EUR',
            ],
        ],
        'placed_at' => now(),
    ]);

    app(OrderEmailService::class)->sendOrderCreated($order);

    Mail::assertSent(OrderLifecycleMail::class, function (OrderLifecycleMail $mail): bool {
        $rows = (array) ($mail->context['rows'] ?? []);

        $hasEntity = collect($rows)->contains(fn (array $row): bool => ($row['label'] ?? '') === 'Entidade MB' && ($row['value'] ?? '') === '12345');
        $hasReference = collect($rows)->contains(fn (array $row): bool => ($row['label'] ?? '') === 'Referencia MB' && ($row['value'] ?? '') === '123 456 789');

        return $hasEntity && $hasReference;
    });
});
