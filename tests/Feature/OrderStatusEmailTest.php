<?php

use App\Mail\OrderLifecycleMail;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

test('sends email to customer when order status changes', function () {
    Mail::fake();

    $user = User::factory()->create([
        'name' => 'Cliente Teste',
        'email' => 'cliente@example.com',
    ]);

    $order = Order::query()->create([
        'user_id' => $user->id,
        'order_number' => 'ORC-TEST-000001',
        'status' => 'awaiting_payment',
        'currency' => 'EUR',
        'vat_rate' => 23,
        'subtotal_ex_vat' => 50,
        'shipping_ex_vat' => 10,
        'payment_fee_ex_vat' => 0,
        'total_ex_vat' => 60,
        'total_inc_vat' => 73.8,
        'shipping_address_snapshot' => ['first_name' => 'Cliente', 'last_name' => 'Teste'],
        'billing_address_snapshot' => ['first_name' => 'Cliente', 'last_name' => 'Teste'],
        'shipping_method_snapshot' => ['name' => 'DPD'],
        'payment_method_snapshot' => ['name' => 'Transferencia'],
        'placed_at' => now(),
    ]);

    $order->status = 'processing';
    $order->save();

    Mail::assertSent(OrderLifecycleMail::class, function (OrderLifecycleMail $mail) use ($order): bool {
        return $mail->order->is($order)
            && ($mail->context['title'] ?? '') === 'Estado da encomenda atualizado';
    });
});

test('does not send email when status stays the same', function () {
    Mail::fake();

    $user = User::factory()->create([
        'name' => 'Cliente Sem Mudanca',
        'email' => 'sem-mudanca@example.com',
    ]);

    $order = Order::query()->create([
        'user_id' => $user->id,
        'order_number' => 'ORC-TEST-000002',
        'status' => 'awaiting_payment',
        'currency' => 'EUR',
        'vat_rate' => 23,
        'subtotal_ex_vat' => 20,
        'shipping_ex_vat' => 5,
        'payment_fee_ex_vat' => 0,
        'total_ex_vat' => 25,
        'total_inc_vat' => 30.75,
        'shipping_address_snapshot' => ['first_name' => 'Cliente', 'last_name' => 'Teste'],
        'billing_address_snapshot' => ['first_name' => 'Cliente', 'last_name' => 'Teste'],
        'shipping_method_snapshot' => ['name' => 'DPD'],
        'payment_method_snapshot' => ['name' => 'Transferencia'],
        'placed_at' => now(),
    ]);

    $order->customer_note = 'Atualizacao sem mudar estado';
    $order->save();

    Mail::assertNothingSent();
});

test('sends payment update email when order status changes to paid', function () {
    Mail::fake();

    $user = User::factory()->create([
        'name' => 'Cliente Pagamento',
        'email' => 'pagamento@example.com',
    ]);

    $order = Order::query()->create([
        'user_id' => $user->id,
        'order_number' => 'ORC-TEST-000003',
        'status' => 'awaiting_payment',
        'currency' => 'EUR',
        'vat_rate' => 23,
        'subtotal_ex_vat' => 30,
        'shipping_ex_vat' => 5,
        'payment_fee_ex_vat' => 0,
        'total_ex_vat' => 35,
        'total_inc_vat' => 43.05,
        'shipping_address_snapshot' => ['first_name' => 'Cliente', 'last_name' => 'Pagamento'],
        'billing_address_snapshot' => ['first_name' => 'Cliente', 'last_name' => 'Pagamento'],
        'shipping_method_snapshot' => ['name' => 'DPD'],
        'payment_method_snapshot' => ['name' => 'SIBS'],
        'placed_at' => now(),
    ]);

    $order->status = 'paid';
    $order->save();

    Mail::assertSent(OrderLifecycleMail::class, function (OrderLifecycleMail $mail) use ($order): bool {
        return $mail->order->is($order)
            && ($mail->context['title'] ?? '') === 'Atualizacao de pagamento da encomenda';
    });
});

