<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Store\StoreCheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StoreOrderController extends Controller
{
    public function __construct(
        private readonly StoreCheckoutService $checkoutService,
    ) {
    }

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

        $availablePaymentMethods = [];
        if ((string) $order->status === 'awaiting_payment') {
            $availablePaymentMethods = $this->checkoutService->paymentMethodsForOrder($order);
        }

        return view('store.account.orders.show', compact('order', 'availablePaymentMethods'));
    }

    public function updatePaymentMethod(Request $request, Order $order): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 401);
        abort_if((int) $order->user_id !== (int) $user->id, 404);

        $data = $request->validate([
            'payment_method_id' => ['required', 'integer'],
        ]);

        try {
            $this->checkoutService->changeOrderPaymentMethod(
                $order->loadMissing('items'),
                (int) $data['payment_method_id'],
                (int) $user->id,
            );
        } catch (\RuntimeException $e) {
            return back()->withErrors(['payment_method_id' => $e->getMessage()]);
        }

        return back()->with('success', 'Metodo de pagamento atualizado.');
    }
}
