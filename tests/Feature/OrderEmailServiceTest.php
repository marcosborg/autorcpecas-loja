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

