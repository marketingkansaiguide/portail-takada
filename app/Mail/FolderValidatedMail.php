<?php

namespace App\Mail;

use App\Models\Folder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class FolderValidatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Folder $folder)
    {
    }

    public function build(): static
    {
        return $this->subject("Nouveau dossier validé : {$this->folder->reference}")
            ->view('emails.folder-validated');
    }
}
