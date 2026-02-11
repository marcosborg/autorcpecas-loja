<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CmsPageContactMail extends Mailable
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
        $subject = 'Contacto CMS - '.($this->payload['page_title'] ?? 'Pagina');

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
            view: 'emails.cms-page-contact',
            with: [
                'payload' => $this->payload,
            ],
        );
    }
}

