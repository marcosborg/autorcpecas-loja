<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StoreOrderController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user, 401);

        $orders = Order::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->paginate(20);

        return view('store.account.orders.index', compact('orders'));
    }

    public function show(Request $request, Order $order): View
    {
        $user = $request->user();
        abort_unless($user, 401);
        abort_if((int) $order->user_id !== (int) $user->id, 404);

        $order->load(['items', 'statusHistory']);

        return view('store.account.orders.show', compact('order'));
    }
}

