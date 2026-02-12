<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\Orders\OrderEmailService;
use App\Services\TpSoftware\TpSoftwareSalesSyncService;
use Illuminate\Support\Facades\Log;

class OrderObserver
{
    public function __construct(
        private readonly OrderEmailService $orderEmails,
        private readonly TpSoftwareSalesSyncService $tpSoftwareSalesSync,
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
            if ($current === 'paid') {
                $this->syncTpSoftwareSale($order);
            }
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

    private function syncTpSoftwareSale(Order $order): void
    {
        try {
            $this->tpSoftwareSalesSync->syncPaidOrder($order);
        } catch (\Throwable $e) {
            Log::warning('TP Software sync failed from OrderObserver', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
