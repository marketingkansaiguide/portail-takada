<?php

namespace App\Filament\Agency\Pages;

use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Group;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Filament\Notifications\Notification;
use Filament\Facades\Filament;
use BackedEnum; // 💡 Import obligatoire pour le typage de l'icône

class Contact extends Page implements HasForms
{
    use InteractsWithForms;

    // 💡 CORRECTION DU TYPAGE ICI : On respecte exactement la signature de la classe parente
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-envelope';
    
    protected static ?string $title = 'Contact / Sur-mesure';
    
    protected static ?string $navigationLabel = 'Nous contacter';
    
    protected static ?int $navigationSort = 100;

    protected string $view = 'filament.agency.pages.contact';

    public ?array $data = [];

    public function mount(): void
    {
        $user = Filament::auth()->user();
        
        $this->form->fill([
            'name' => $user ? $user->name : '',
            'email' => $user ? $user->email : '',
            'agency_name' => ($user && $user->agency) ? $user->agency->name : '',
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Envoyez-nous un message')
                    ->description('Besoin d\'un devis sur-mesure ou d\'une assistance ? Notre équipe est là pour vous.')
                    ->schema([
                        Group::make()->schema([
                            TextInput::make('name')
                                ->label('Votre nom')
                                ->required(),
                                
                            TextInput::make('agency_name')
                                ->label('Nom de votre agence')
                                ->required(),
                                
                            TextInput::make('email')
                                ->label('Adresse e-mail')
                                ->email()
                                ->required(),
                                
                            TextInput::make('subject')
                                ->label('Sujet de votre demande')
                                ->placeholder('Ex: Demande de devis groupe...')
                                ->required(),
                        ])->columns(2),

                        Textarea::make('message')
                            ->label('Votre message')
                            ->placeholder('Détaillez votre demande ici...')
                            ->required()
                            ->rows(6),
                    ])
            ]);
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('send')
                ->label('Envoyer le message')
                ->submit('send') 
                ->color('primary')
                ->icon('heroicon-m-paper-airplane'),
        ];
    }

    public function send(): void
    {
        $data = $this->form->getState();

        try {
            Mail::raw("Nouvelle demande de contact / Sur-mesure (Portail B2B)\n\n"
                . "Nom du contact : {$data['name']}\n"
                . "Agence : {$data['agency_name']}\n"
                . "Email : {$data['email']}\n\n"
                . "Sujet : {$data['subject']}\n\n"
                . "Message :\n{$data['message']}", 
                function ($message) use ($data) {
                    $message->to('resa@kansai-guide.com')
                            ->subject('Demande Portail Takada : ' . $data['subject'])
                            ->replyTo($data['email']);
                }
            );

            Notification::make()
                ->title('Message envoyé avec succès !')
                ->body('Notre équipe reviendra vers vous dans les plus brefs délais.')
                ->success()
                ->send();

            $this->form->fill([
                'name' => $data['name'],
                'email' => $data['email'],
                'agency_name' => $data['agency_name'],
                'subject' => '',
                'message' => '',
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'envoi du mail de contact : ' . $e->getMessage());
            
            Notification::make()
                ->title('Erreur lors de l\'envoi')
                ->body('Veuillez réessayer plus tard ou nous contacter directement par mail.')
                ->danger()
                ->send();
        }
    }
}