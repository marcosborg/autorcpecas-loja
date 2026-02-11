<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StoreAccountController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user, 401);

        $recentOrders = Order::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->limit(5)
            ->get();

        $addresses = $user->addresses()->orderByDesc('is_default_shipping')->orderByDesc('is_default_billing')->latest('id')->limit(5)->get();

        return view('store.account.dashboard', [
            'recentOrders' => $recentOrders,
            'addresses' => $addresses,
        ]);
    }
}

