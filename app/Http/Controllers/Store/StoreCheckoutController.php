<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\CustomerAddress;
use App\Services\Store\CartService;
use App\Services\Store\StoreCheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StoreCheckoutController extends Controller
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly StoreCheckoutService $checkoutService,
    ) {
    }

    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        $cart = $this->cartService->openCartFor($user)->load('items');
        if ($cart->items->count() === 0) {
            return redirect(url('/loja/carrinho'))->withErrors(['checkout' => 'Carrinho vazio.']);
        }

        $addresses = $user->addresses()->orderByDesc('is_default_shipping')->orderByDesc('is_default_billing')->get();
        if ($addresses->count() === 0) {
            return redirect(url('/loja/conta/moradas/create'))->withErrors(['checkout' => 'Cria uma morada antes de finalizar.']);
        }

        $shippingAddressId = (int) ($request->query('shipping_address_id') ?: ($addresses->firstWhere('is_default_shipping', true)->id ?? $addresses->first()->id));
        $billingAddressId = (int) ($request->query('billing_address_id') ?: ($addresses->firstWhere('is_default_billing', true)->id ?? $shippingAddressId));

        $shippingAddress = $addresses->firstWhere('id', $shippingAddressId) ?? $addresses->first();
        $billingAddress = $addresses->firstWhere('id', $billingAddressId) ?? $shippingAddress;

        $context = $this->checkoutService->quote($user, $shippingAddress);

        return view('store.checkout.index', [
            'cart' => $context['cart'],
            'totals' => $context['totals'],
            'quote' => $context['quote'],
            'addresses' => $addresses,
            'shippingAddressId' => (int) $shippingAddress->id,
            'billingAddressId' => (int) $billingAddress->id,
        ]);
    }

    public function place(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        $data = $request->validate([
            'shipping_address_id' => ['required', 'integer'],
            'billing_address_id' => ['required', 'integer'],
            'shipping_carrier_id' => ['required', 'integer'],
            'payment_method_id' => ['required', 'integer'],
            'customer_note' => ['nullable', 'string', 'max:1500'],
        ]);

        $shippingAddress = CustomerAddress::query()
            ->where('user_id', $user->id)
            ->findOrFail((int) $data['shipping_address_id']);
        $billingAddress = CustomerAddress::query()
            ->where('user_id', $user->id)
            ->findOrFail((int) $data['billing_address_id']);

        $order = $this->checkoutService->placeOrder(
            $user,
            $shippingAddress,
            $billingAddress,
            (int) $data['shipping_carrier_id'],
            (int) $data['payment_method_id'],
            (string) ($data['customer_note'] ?? null),
        );

        return redirect(url('/loja/conta/encomendas/'.$order->id))->with('success', 'Encomenda criada com sucesso.');
    }
}

