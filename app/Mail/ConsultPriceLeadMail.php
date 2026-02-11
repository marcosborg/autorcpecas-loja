<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ConsultPriceLeadMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param array<string, string> $payload
     */
    public function __construct(public array $payload)
    {
    }

    public function envelope(): Envelope
    {
        $subjectTitle = $this->payload['product_title'] ?? 'Produto';
        $subjectRef = trim((string) ($this->payload['product_reference'] ?? ''));
        $subject = 'Pedido sob consulta - '.$subjectTitle;
        if ($subjectRef !== '') {
            $subject .= ' (Ref: '.$subjectRef.')';
        }

        return new Envelope(
            subject: $subject,
            replyTo: [
                $this->payload['customer_email'] ?? '',
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.consult-price-lead',
            with: [
                'payload' => $this->payload,
            ],
        );
    }
}
