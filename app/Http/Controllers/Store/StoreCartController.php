<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Services\Catalog\CatalogProvider;
use App\Services\Store\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StoreCartController extends Controller
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly CatalogProvider $catalog,
    ) {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user, 401);

        $cart = $this->cartService->openCartFor($user)->load('items');
        $totals = $this->cartService->totals($cart);

        return view('store.cart.index', compact('cart', 'totals'));
    }

    public function add(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        $data = $request->validate([
            'product_key' => ['required', 'string', 'max:120'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:99'],
        ]);

        $product = $this->catalog->product((string) $data['product_key']);
        if (! is_array($product)) {
            return back()->withErrors(['cart' => 'Produto nao encontrado.']);
        }

        $this->cartService->addProduct($user, $product, (int) ($data['quantity'] ?? 1));

        return redirect(url('/loja/carrinho'))->with('success', 'Produto adicionado ao carrinho.');
    }

    public function update(Request $request, CartItem $item): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 401);
        abort_if((int) $item->cart->user_id !== (int) $user->id, 404);

        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:0', 'max:99'],
        ]);

        $this->cartService->updateQuantity($user, $item->id, (int) $data['quantity']);

        return back()->with('success', 'Carrinho atualizado.');
    }

    public function destroy(Request $request, CartItem $item): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 401);
        abort_if((int) $item->cart->user_id !== (int) $user->id, 404);

        $this->cartService->removeItem($user, $item->id);

        return back()->with('success', 'Item removido.');
    }
}

