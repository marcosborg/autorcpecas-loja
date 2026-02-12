<?php

namespace App\Services\Store;

use App\Models\Cart;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\User;
use App\Services\Checkout\CheckoutOptionsService;
use App\Services\Orders\OrderEmailService;
use App\Services\Payments\SibsCheckoutService;
use App\Services\Tax\CheckoutVatService;
use Illuminate\Support\Facades\DB;

class StoreCheckoutService
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly CheckoutOptionsService $checkoutOptions,
        private readonly OrderEmailService $orderEmails,
        private readonly SibsCheckoutService $sibsCheckout,
        private readonly CheckoutVatService $vatService,
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
            (string) ($shippingAddress->zone_code ?? ''),
            (string) ($shippingAddress->postal_code ?? ''),
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

        $vatRate = $this->vatService->resolveVatRate($shippingAddress, $billingAddress);
        $subtotal = (float) $context['totals']['subtotal_ex_vat'];
        $shipping = (float) ($carrier['price_ex_vat'] ?? 0);
        $paymentFee = (float) ($payment['fee_ex_vat'] ?? 0);
        $totalExVat = $subtotal + $shipping + $paymentFee;
        $totalIncVat = round($totalExVat * (1 + ($vatRate / 100)), 2);

        $order = DB::transaction(function () use (
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
            $order->payment_method_snapshot = $this->decoratePaymentSnapshot(
                $payment,
                $order->order_number,
                (float) $totalIncVat,
                (string) $order->currency,
            );
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

        $this->orderEmails->sendOrderCreated($order);

        return $order;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function paymentMethodsForOrder(Order $order): array
    {
        $shippingAddress = (array) ($order->shipping_address_snapshot ?? []);
        $countryIso2 = trim((string) ($shippingAddress['country_iso2'] ?? 'PT'));
        $zoneCode = trim((string) ($shippingAddress['zone_code'] ?? ''));
        $postalCode = trim((string) ($shippingAddress['postal_code'] ?? ''));
        $weightKg = (float) $order->items->sum(fn (OrderItem $item): float => (float) $item->weight_kg * (int) $item->quantity);

        $quote = $this->checkoutOptions->quote(
            (float) $order->subtotal_ex_vat,
            $weightKg,
            $countryIso2 !== '' ? $countryIso2 : 'PT',
            $zoneCode !== '' ? $zoneCode : null,
            $postalCode !== '' ? $postalCode : null,
        );

        return array_values(array_filter(
            (array) ($quote['payment_methods'] ?? []),
            fn ($m): bool => is_array($m)
        ));
    }

    public function changeOrderPaymentMethod(Order $order, int $paymentMethodId, ?int $actorUserId = null): Order
    {
        if ((string) $order->status !== 'awaiting_payment') {
            throw new \RuntimeException('So e possivel alterar metodo de pagamento em encomendas por pagar.');
        }

        $methods = $this->paymentMethodsForOrder($order);
        $newPayment = collect($methods)->first(fn ($p) => (int) ($p['id'] ?? 0) === $paymentMethodId);
        if (! is_array($newPayment)) {
            throw new \RuntimeException('Metodo de pagamento invalido para esta encomenda.');
        }

        $oldPayment = (array) ($order->payment_method_snapshot ?? []);
        $oldName = trim((string) ($oldPayment['name'] ?? '-'));
        $newName = trim((string) ($newPayment['name'] ?? '-'));

        $paymentFee = (float) ($newPayment['fee_ex_vat'] ?? 0);
        $totalExVat = (float) $order->subtotal_ex_vat + (float) $order->shipping_ex_vat + $paymentFee;
        $vatRate = (float) $order->vat_rate;
        $totalIncVat = round($totalExVat * (1 + ($vatRate / 100)), 2);

        $snapshot = $this->decoratePaymentSnapshot(
            $newPayment,
            (string) $order->order_number,
            $totalIncVat,
            (string) $order->currency,
        );

        DB::transaction(function () use ($order, $paymentFee, $totalExVat, $totalIncVat, $snapshot, $oldName, $newName, $actorUserId): void {
            $order->payment_fee_ex_vat = round($paymentFee, 2);
            $order->total_ex_vat = round($totalExVat, 2);
            $order->total_inc_vat = round($totalIncVat, 2);
            $order->payment_method_snapshot = $snapshot;
            $order->save();

            OrderStatusHistory::query()->create([
                'order_id' => $order->id,
                'status' => (string) $order->status,
                'note' => 'Metodo de pagamento alterado: '.$oldName.' -> '.$newName.'.',
                'created_by_user_id' => $actorUserId,
            ]);
        });

        return $order->fresh(['items', 'statusHistory']);
    }

    public function syncOrderAddressesFromDefaults(Order $order, User $user, ?int $actorUserId = null): Order
    {
        if ((int) $order->user_id !== (int) $user->id) {
            throw new \RuntimeException('Encomenda invalida para este utilizador.');
        }

        if ((string) $order->status !== 'awaiting_payment') {
            return $order->fresh(['items', 'statusHistory']);
        }

        $addresses = $user->addresses()
            ->orderByDesc('is_default_shipping')
            ->orderByDesc('is_default_billing')
            ->orderBy('id')
            ->get();

        if ($addresses->count() === 0) {
            return $order->fresh(['items', 'statusHistory']);
        }

        $shippingAddress = $addresses->firstWhere('is_default_shipping', true) ?? $addresses->first();
        $billingAddress = $addresses->firstWhere('is_default_billing', true) ?? $shippingAddress;

        $shippingSnapshot = $shippingAddress->snapshot();
        $billingSnapshot = $billingAddress->snapshot();

        $currentShipping = (array) ($order->shipping_address_snapshot ?? []);
        $currentBilling = (array) ($order->billing_address_snapshot ?? []);
        if ($currentShipping === $shippingSnapshot && $currentBilling === $billingSnapshot) {
            return $order->fresh(['items', 'statusHistory']);
        }

        $weightKg = (float) $order->items->sum(fn (OrderItem $item): float => (float) $item->weight_kg * (int) $item->quantity);
        $quote = $this->checkoutOptions->quote(
            (float) $order->subtotal_ex_vat,
            $weightKg,
            (string) ($shippingSnapshot['country_iso2'] ?? 'PT'),
            (string) ($shippingSnapshot['zone_code'] ?? ''),
            (string) ($shippingSnapshot['postal_code'] ?? ''),
        );

        $currentCarrier = (array) ($order->shipping_method_snapshot ?? []);
        $currentCarrierId = (int) ($currentCarrier['id'] ?? 0);
        $newCarrier = collect((array) ($quote['carriers'] ?? []))
            ->first(fn ($carrier): bool => (int) ($carrier['id'] ?? 0) === $currentCarrierId);
        if (! is_array($newCarrier)) {
            $newCarrier = collect((array) ($quote['carriers'] ?? []))->first();
        }

        $paymentOptions = (array) ($quote['payment_methods'] ?? []);
        $currentPaymentCode = (string) data_get($order->payment_method_snapshot, 'code', '');
        $newPayment = collect($paymentOptions)->first(fn ($payment): bool => (string) ($payment['code'] ?? '') === $currentPaymentCode);
        if (! is_array($newPayment)) {
            $newPayment = collect($paymentOptions)->first();
        }

        $shippingExVat = (float) ($newCarrier['price_ex_vat'] ?? $order->shipping_ex_vat);
        $paymentFeeExVat = (float) ($newPayment['fee_ex_vat'] ?? $order->payment_fee_ex_vat);
        $vatRate = $this->vatService->resolveVatRateFromSnapshot($shippingSnapshot, $billingSnapshot);
        $totalExVat = (float) $order->subtotal_ex_vat + $shippingExVat + $paymentFeeExVat;
        $totalIncVat = round($totalExVat * (1 + ($vatRate / 100)), 2);
        $paymentSnapshot = is_array($newPayment)
            ? $this->decoratePaymentSnapshot($newPayment, (string) $order->order_number, $totalIncVat, (string) $order->currency)
            : (array) ($order->payment_method_snapshot ?? []);

        DB::transaction(function () use ($order, $shippingSnapshot, $billingSnapshot, $actorUserId, $newCarrier, $paymentSnapshot, $shippingExVat, $paymentFeeExVat, $vatRate, $totalExVat, $totalIncVat): void {
            $order->shipping_address_snapshot = $shippingSnapshot;
            $order->billing_address_snapshot = $billingSnapshot;
            if (is_array($newCarrier)) {
                $order->shipping_method_snapshot = $newCarrier;
            }
            $order->payment_method_snapshot = $paymentSnapshot;
            $order->shipping_ex_vat = round($shippingExVat, 2);
            $order->payment_fee_ex_vat = round($paymentFeeExVat, 2);
            $order->vat_rate = round($vatRate, 2);
            $order->total_ex_vat = round($totalExVat, 2);
            $order->total_inc_vat = round($totalIncVat, 2);
            $order->save();

            OrderStatusHistory::query()->create([
                'order_id' => $order->id,
                'status' => (string) $order->status,
                'note' => 'Moradas da encomenda sincronizadas com as moradas atuais do cliente.',
                'created_by_user_id' => $actorUserId,
            ]);
        });

        return $order->fresh(['items', 'statusHistory']);
    }

    /**
     * @return array{message: string, email_sent: bool, redirect_url?: string}
     */
    public function executeOrderPayment(Order $order, ?int $actorUserId = null): array
    {
        if ((string) $order->status !== 'awaiting_payment') {
            throw new \RuntimeException('So e possivel executar pagamento em encomendas por pagar.');
        }

        $payment = is_array($order->payment_method_snapshot) ? $order->payment_method_snapshot : [];
        $code = trim((string) ($payment['code'] ?? ''));
        $gateway = trim((string) data_get($payment, 'meta.gateway', ''));

        if ($code === '') {
            throw new \RuntimeException('Metodo de pagamento invalido nesta encomenda.');
        }

        if (str_starts_with($code, 'sibs_')) {
            $result = $this->sibsCheckout->startCheckoutForOrder($order, $actorUserId);

            if ($code === 'sibs_multibanco') {
                $this->orderEmails->sendPaymentUpdated($order->fresh(), null);
            }

            return [
                'message' => (string) ($result['message'] ?? 'Pagamento SIBS iniciado.'),
                'email_sent' => $code === 'sibs_multibanco',
                'redirect_url' => isset($result['redirect_url']) ? (string) $result['redirect_url'] : null,
            ];
        }

        if ($gateway === 'manual_bank_transfer') {
            OrderStatusHistory::query()->create([
                'order_id' => $order->id,
                'status' => (string) $order->status,
                'note' => 'Pagamento iniciado por Transferencia Bancaria.',
                'created_by_user_id' => $actorUserId,
            ]);

            return [
                'message' => 'Usa os dados de transferencia para concluir o pagamento.',
                'email_sent' => false,
            ];
        }

        return [
            'message' => 'Metodo de pagamento atualizado.',
            'email_sent' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $payment
     * @return array<string, mixed>
     */
    private function decoratePaymentSnapshot(array $payment, string $orderNumber, float $amount, string $currency): array
    {
        if ((string) ($payment['code'] ?? '') !== 'sibs_multibanco') {
            return $payment;
        }

        $meta = is_array($payment['meta'] ?? null) ? $payment['meta'] : [];
        $entity = preg_replace('/\D+/', '', (string) ($meta['payment_entity'] ?? ''));
        $entity = is_string($entity) ? trim($entity) : '';
        $paymentType = trim((string) ($meta['payment_type'] ?? ''));

        // Generates a deterministic 9-digit reference per order.
        $seed = abs(crc32('mb|'.$orderNumber));
        $referenceDigits = str_pad((string) ($seed % 1000000000), 9, '0', STR_PAD_LEFT);
        $referenceDisplay = substr($referenceDigits, 0, 3).' '.substr($referenceDigits, 3, 3).' '.substr($referenceDigits, 6, 3);

        $payment['payment_instructions'] = [
            'entity' => $entity,
            'payment_type' => $paymentType,
            'reference' => $referenceDigits,
            'reference_display' => $referenceDisplay,
            'amount' => round($amount, 2),
            'currency' => $currency !== '' ? $currency : 'EUR',
        ];

        return $payment;
    }
}
