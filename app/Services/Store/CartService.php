<?php

namespace App\Services\Store;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CartService
{
    public function openCartFor(User $user): Cart
    {
        $cart = Cart::query()
            ->where('user_id', $user->id)
            ->where('status', 'open')
            ->latest('id')
            ->first();

        if ($cart) {
            return $cart->load('items');
        }

        return Cart::query()->create([
            'user_id' => $user->id,
            'status' => 'open',
            'currency' => 'EUR',
        ])->load('items');
    }

    /**
     * @param  array<string, mixed>  $product
     */
    public function addProduct(User $user, array $product, int $quantity = 1): Cart
    {
        $cart = $this->openCartFor($user);

        $productKey = (string) (($product['id'] ?? null) ?: ($product['reference'] ?? ''));
        if ($productKey === '') {
            throw new \InvalidArgumentException('Produto sem chave valida para carrinho.');
        }

        $unitPriceExVat = $this->toFloat($product['price_ex_vat'] ?? ($product['price'] ?? 0));
        $weightKg = $this->extractWeightKg($product);

        $item = CartItem::query()->firstOrNew([
            'cart_id' => $cart->id,
            'product_key' => $productKey,
        ]);

        $item->reference = (string) ($product['reference'] ?? '');
        $item->title = (string) ($product['title'] ?? 'Produto');
        $item->unit_price_ex_vat = $unitPriceExVat;
        // Pecas usadas: cada item e unico, sem multiplas quantidades.
        $item->quantity = 1;
        $item->weight_kg = $weightKg;
        $item->product_payload = $product;
        $item->save();

        return $cart->fresh('items');
    }

    public function updateQuantity(User $user, int $itemId, int $quantity): Cart
    {
        $cart = $this->openCartFor($user);
        $item = $cart->items()->whereKey($itemId)->firstOrFail();

        if ($quantity <= 0) {
            $item->delete();
        } else {
            $item->quantity = $quantity;
            $item->save();
        }

        return $cart->fresh('items');
    }

    public function removeItem(User $user, int $itemId): Cart
    {
        return $this->updateQuantity($user, $itemId, 0);
    }

    public function clear(User $user): void
    {
        $cart = $this->openCartFor($user);
        $cart->items()->delete();
    }

    /**
     * @return array{
     *   subtotal_ex_vat: float,
     *   total_qty: int,
     *   total_weight_kg: float
     * }
     */
    public function totals(Cart $cart): array
    {
        $subtotal = 0.0;
        $qty = 0;
        $weight = 0.0;

        foreach ($cart->items as $item) {
            $lineQty = (int) $item->quantity;
            $qty += $lineQty;
            $subtotal += ((float) $item->unit_price_ex_vat * $lineQty);
            $weight += ((float) $item->weight_kg * $lineQty);
        }

        return [
            'subtotal_ex_vat' => round($subtotal, 2),
            'total_qty' => $qty,
            'total_weight_kg' => round($weight, 3),
        ];
    }

    public function markConverted(Cart $cart): void
    {
        DB::transaction(function () use ($cart): void {
            $cart->status = 'converted';
            $cart->save();
        });
    }

    /**
     * @param  array<string, mixed>  $product
     */
    private function extractWeightKg(array $product): float
    {
        $candidates = [
            $product['weight_kg'] ?? null,
            $product['weight'] ?? null,
            data_get($product, 'raw.weight'),
            data_get($product, 'raw.net_weight'),
            data_get($product, 'raw.gross_weight'),
        ];

        foreach ($candidates as $value) {
            if (is_numeric($value) && (float) $value > 0) {
                return round((float) $value, 3);
            }
        }

        return 1.0;
    }

    private function toFloat(mixed $value): float
    {
        return is_numeric($value) ? round((float) $value, 2) : 0.0;
    }
}
