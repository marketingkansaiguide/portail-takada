<?php

namespace App\Mail;

use App\Models\Folder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NewChatMessageForAdminMail extends Mailable
{
    use Queueable, SerializesModels;

    public $folder;

    public function __construct(Folder $folder)
    {
        $this->folder = $folder;
    }

    public function build()
    {
        return $this->subject('L\'agence a répondu sur le dossier ' . $this->folder->folder_name)
                    ->view('emails.new-chat-message-admin');
    }
}