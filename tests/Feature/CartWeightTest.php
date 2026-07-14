<?php

use App\Models\ProductCategoryWeight;
use App\Models\User;
use App\Services\Store\CartService;

test('stores the telepecas parts weight instead of the old one kilogram fallback', function () {
    $user = User::factory()->create();

    $cart = app(CartService::class)->addProduct($user, [
        'id' => 100,
        'title' => 'Motor completo',
        'price_ex_vat' => 500,
        'piece_name' => 'Motor',
        'raw' => ['parts_weight' => 150],
    ]);

    expect($cart->items->first()->weight_kg)->toBe(150.0)
        ->and($cart->items->first()->weight_source)->toBe('telepecas')
        ->and(app(CartService::class)->totals($cart)['total_weight_kg'])->toBe(150.0);
});

test('uses an active category estimate when telepecas weight is zero', function () {
    ProductCategoryWeight::query()->create([
        'category' => 'Caixa',
        'weight_kg' => 75,
        'active' => true,
    ]);
    $user = User::factory()->create();

    $cart = app(CartService::class)->addProduct($user, [
        'id' => 101,
        'title' => 'Caixa de velocidades',
        'price_ex_vat' => 400,
        'piece_name' => 'Caixa',
        'raw' => ['parts_weight' => 0],
    ]);

    expect($cart->items->first()->weight_kg)->toBe(75.0)
        ->and($cart->items->first()->weight_source)->toBe('category_estimate')
        ->and(app(CartService::class)->totals($cart)['weight_known'])->toBeTrue();
});

test('marks weight as missing when neither telepecas nor a category estimate provides it', function () {
    $user = User::factory()->create();

    $cart = app(CartService::class)->addProduct($user, [
        'id' => 102,
        'title' => 'Peça sem peso',
        'price_ex_vat' => 20,
        'piece_name' => 'Desconhecida',
        'raw' => ['parts_weight' => 0],
    ]);

    expect($cart->items->first()->weight_kg)->toBe(0.0)
        ->and($cart->items->first()->weight_source)->toBe('missing')
        ->and(app(CartService::class)->totals($cart)['weight_known'])->toBeFalse();
});
