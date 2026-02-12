<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderLifecycleMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        public Order $order,
        public array $context = [],
    ) {
    }

    public function envelope(): Envelope
    {
        $subject = trim((string) ($this->context['subject'] ?? 'Atualizacao da sua encomenda'));

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.orders.lifecycle',
            with: [
                'order' => $this->order,
                'context' => $this->context,
            ],
        );
    }
}
