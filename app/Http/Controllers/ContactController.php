<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class ContactController extends Controller
{
    /**
     * Affiche la page de contact.
     */
    public function index()
    {
        return view('contact');
    }

    /**
     * Traite la soumission du formulaire de contact.
     */
    public function send(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'agency_name' => 'nullable|string|max:255',
            'email' => 'required|email|max:255',
            'subject' => 'required|string|max:255',
            'message' => 'required|string|min:10',
        ]);

        try {
            Mail::raw("Nouvelle demande de contact / Sur-mesure (Portail B2B)\n\n"
                . "Nom du contact : {$validated['name']}\n"
                . "Agence : " . ($validated['agency_name'] ?? 'Non renseignée') . "\n"
                . "Email : {$validated['email']}\n\n"
                . "Sujet : {$validated['subject']}\n\n"
                . "Message :\n{$validated['message']}", 
                function ($message) use ($validated) {
                    $message->to('resa@kansai-guide.com')
                            ->subject('Demande Portail Takada : ' . $validated['subject'])
                            ->replyTo($validated['email']);
                }
            );
        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'envoi du mail de contact : ' . $e->getMessage());
        }

        return back()->with('success', 'Votre demande a bien été envoyée ! Notre équipe reviendra vers vous dans les plus brefs délais.');
    }
}