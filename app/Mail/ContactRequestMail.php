<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContactRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public array $data)
    {
    }

    public function build(): static
    {
        return $this->subject('Demande Portail Takada : ' . $this->data['subject'])
            ->replyTo($this->data['email'], $this->data['name'])
            ->view('emails.contact-request');
    }
}
