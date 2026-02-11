<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Services\Checkout\CheckoutOptionsService;
use Illuminate\Http\Request;

class StoreCheckoutSimulatorController extends Controller
{
    public function index(Request $request, CheckoutOptionsService $checkout)
    {
        $subtotal = (float) $request->query('subtotal', 120.00);
        $weight = (float) $request->query('weight', 8.5);
        $country = (string) $request->query('country', 'PT');

        $quote = $checkout->quote($subtotal, $weight, $country);

        return view('store.checkout-simulator', [
            'subtotal' => $subtotal,
            'weight' => $weight,
            'country' => mb_strtoupper(trim($country), 'UTF-8'),
            'quote' => $quote,
        ]);
    }
}

