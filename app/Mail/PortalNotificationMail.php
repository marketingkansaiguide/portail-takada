<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PortalNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $subjectLine,
        public string $heading,
        public string $intro,
        public array $details = [],
        public ?string $messageText = null,
        public ?string $actionLabel = null,
        public ?string $actionUrl = null,
    ) {
    }

    public function build(): static
    {
        return $this->subject($this->subjectLine)
            ->view('emails.portal-notification');
    }
}
