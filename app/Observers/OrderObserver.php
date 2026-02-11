<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\Orders\OrderEmailService;

class OrderObserver
{
    public function __construct(
        private readonly OrderEmailService $orderEmails,
    ) {
    }

    public function updated(Order $order): void
    {
        if (! $order->wasChanged('status')) {
            return;
        }

        $previous = (string) $order->getOriginal('status');
        $current = (string) $order->status;

        if ($this->isPaymentStatus($current)) {
            $this->orderEmails->sendPaymentUpdated($order, $previous);
            return;
        }

        $this->orderEmails->sendStatusUpdated($order, $previous);
    }

    private function isPaymentStatus(string $status): bool
    {
        return in_array($status, [
            'awaiting_payment',
            'paid',
            'refunded',
            'cancelled',
        ], true);
    }
}
