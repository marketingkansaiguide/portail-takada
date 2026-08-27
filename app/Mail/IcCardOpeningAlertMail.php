<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class IcCardOpeningAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public $items;

    public function __construct($items)
    {
        $this->items = $items;
    }

    public function envelope(): Envelope
    {
        $count = count($this->items);

        return new Envelope(
            subject: __('🚨 [ALERTE CARTES IC] Ouverture des ventes atteinte - :count prestation(s) à commander', ['count' => $count]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ic-card-opening-alert',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
