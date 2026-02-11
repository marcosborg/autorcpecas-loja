<?php

namespace App\Services\Store;

use App\Models\Cart;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\User;
use App\Services\Checkout\CheckoutOptionsService;
use Illuminate\Support\Facades\DB;

class StoreCheckoutService
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly CheckoutOptionsService $checkoutOptions,
    ) {
    }

    /**
     * @return array{
     *   cart: Cart,
     *   totals: array<string, mixed>,
     *   quote: array<string, mixed>
     * }
     */
    public function quote(User $user, CustomerAddress $shippingAddress): array
    {
        $cart = $this->cartService->openCartFor($user)->load('items');
        $totals = $this->cartService->totals($cart);

        $quote = $this->checkoutOptions->quote(
            (float) $totals['subtotal_ex_vat'],
            (float) $totals['total_weight_kg'],
            (string) $shippingAddress->country_iso2,
        );

        return [
            'cart' => $cart,
            'totals' => $totals,
            'quote' => $quote,
        ];
    }

    public function placeOrder(
        User $user,
        CustomerAddress $shippingAddress,
        CustomerAddress $billingAddress,
        int $shippingCarrierId,
        int $paymentMethodId,
        ?string $customerNote = null,
    ): Order {
        $context = $this->quote($user, $shippingAddress);
        /** @var Cart $cart */
        $cart = $context['cart'];

        if ($cart->items->count() === 0) {
            throw new \RuntimeException('Carrinho vazio.');
        }

        $carrier = collect($context['quote']['carriers'] ?? [])->first(fn ($c) => (int) ($c['id'] ?? 0) === $shippingCarrierId);
        if (! is_array($carrier)) {
            throw new \RuntimeException('Transportadora invalida para este checkout.');
        }

        $payment = collect($context['quote']['payment_methods'] ?? [])->first(fn ($p) => (int) ($p['id'] ?? 0) === $paymentMethodId);
        if (! is_array($payment)) {
            throw new \RuntimeException('Metodo de pagamento invalido para este checkout.');
        }

        $vatRate = 23.0;
        $subtotal = (float) $context['totals']['subtotal_ex_vat'];
        $shipping = (float) ($carrier['price_ex_vat'] ?? 0);
        $paymentFee = (float) ($payment['fee_ex_vat'] ?? 0);
        $totalExVat = $subtotal + $shipping + $paymentFee;
        $totalIncVat = round($totalExVat * (1 + ($vatRate / 100)), 2);

        return DB::transaction(function () use (
            $user,
            $cart,
            $shippingAddress,
            $billingAddress,
            $carrier,
            $payment,
            $customerNote,
            $vatRate,
            $subtotal,
            $shipping,
            $paymentFee,
            $totalExVat,
            $totalIncVat
        ): Order {
            $order = Order::query()->create([
                'user_id' => $user->id,
                'order_number' => 'TMP-'.strtoupper(bin2hex(random_bytes(5))),
                'status' => 'awaiting_payment',
                'currency' => 'EUR',
                'vat_rate' => $vatRate,
                'subtotal_ex_vat' => round($subtotal, 2),
                'shipping_ex_vat' => round($shipping, 2),
                'payment_fee_ex_vat' => round($paymentFee, 2),
                'total_ex_vat' => round($totalExVat, 2),
                'total_inc_vat' => round($totalIncVat, 2),
                'shipping_address_snapshot' => $shippingAddress->snapshot(),
                'billing_address_snapshot' => $billingAddress->snapshot(),
                'shipping_method_snapshot' => $carrier,
                'payment_method_snapshot' => $payment,
                'customer_note' => $customerNote ? trim($customerNote) : null,
                'placed_at' => now(),
            ]);

            $order->order_number = sprintf('ORC-%s-%06d', now()->format('Ymd'), $order->id);
            $order->save();

            foreach ($cart->items as $item) {
                OrderItem::query()->create([
                    'order_id' => $order->id,
                    'product_key' => $item->product_key,
                    'reference' => $item->reference,
                    'title' => $item->title,
                    'quantity' => (int) $item->quantity,
                    'unit_price_ex_vat' => (float) $item->unit_price_ex_vat,
                    'line_total_ex_vat' => round((float) $item->unit_price_ex_vat * (int) $item->quantity, 2),
                    'weight_kg' => (float) $item->weight_kg,
                    'payload' => $item->product_payload,
                ]);
            }

            OrderStatusHistory::query()->create([
                'order_id' => $order->id,
                'status' => 'awaiting_payment',
                'note' => 'Encomenda criada no checkout.',
                'created_by_user_id' => $user->id,
            ]);

            $this->cartService->markConverted($cart);

            return $order->fresh(['items']);
        });
    }
}
