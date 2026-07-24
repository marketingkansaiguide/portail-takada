<?php

namespace App\Filament\Agency\Pages;

use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Group;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Filament\Notifications\Notification;
use Filament\Facades\Filament;
use BackedEnum;
use App\Models\Folder;
use App\Models\FolderMessage;
use App\Models\FolderHistory;
use App\Models\Setting;
use App\Mail\NewChatMessageForAdminMail;

class Contact extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $slug = 'assistance-sur-mesure';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-envelope';
    
    protected static ?string $title = 'Assistance & Contact';
    protected static ?string $navigationLabel = 'Nous contacter';
    protected static ?int $navigationSort = 100;

    protected string $view = 'filament.agency.pages.contact';

    public ?array $data = [];

    public function getSubheading(): ?string
    {
        return __('Contactez notre équipe pour une nouvelle demande sur-mesure ou sélectionnez un dossier en cours pour y ajouter un message.');
    }

    public function mount(): void
    {
        $user = Filament::auth()->user();
        
        $this->form->fill([
            'name' => $user ? $user->name : '',
            'email' => $user ? $user->email : '',
            'agency_name' => ($user && $user->agency) ? $user->agency->name : '',
            'folder_id' => null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make(__('Envoyez-nous un message'))
                    ->schema([
                        Group::make()->schema([
                            TextInput::make('name')
                                ->label(__('Votre nom'))
                                ->required(),
                                
                            TextInput::make('agency_name')
                                ->label(__('Nom de votre agence'))
                                ->required(),
                                
                            TextInput::make('email')
                                ->label(__('Adresse e-mail'))
                                ->email()
                                ->required(),
                                
                            TextInput::make('subject')
                                ->label(__('Sujet de votre demande'))
                                ->required(),
                        ])->columns(2),

                        Select::make('folder_id')
                            ->label(__('Concerne un dossier existant ?'))
                            ->placeholder(__('Non, c\'est une nouvelle demande'))
                            ->options(function () {
                                $user = Filament::auth()->user();
                                if (!$user || !$user->agency_id) return [];
                                
                                return Folder::where('agency_id', $user->agency_id)
                                    ->whereNotIn('status', ['cancelled', 'completed'])
                                    ->orderBy('created_at', 'desc')
                                    ->pluck('folder_name', 'id');
                            })
                            ->searchable()
                            ->preload()
                            ->columnSpanFull(),

                        Textarea::make('message')
                            ->label(__('Votre message'))
                            ->placeholder(__('Détaillez votre demande ici...'))
                            ->required()
                            ->rows(6)
                            ->columnSpanFull(),
                    ])
            ]);
    }

    public function send(): void
    {
        $data = $this->form->getState();
        $user = Filament::auth()->user();

        if ($user && $user->agency_id) {
            try {
                $folder = null;

                // 💡 CAS 1 : L'AGENCE A SÉLECTIONNÉ UN DOSSIER
                if (!empty($data['folder_id'])) {
                    $folder = Folder::find($data['folder_id']);
                } 
                // 💡 CAS 2 : NOUVELLE DEMANDE (On injecte des données par défaut pour ne pas bloquer la DB)
                else {
                    $folder = Folder::create([
                        'agency_id' => $user->agency_id,
                        'main_seller_id' => $user->id,
                        'folder_name' => 'Demande : ' . $data['subject'],
                        'lead_traveler_name' => 'À définir (' . $data['name'] . ')',
                        'status' => 'pending', 
                        'total_price' => 0,
                        'folder_fee' => 0,
                        'pax_adults' => 1,                // Défaut obligatoire
                        'pax_children' => 0,              // Défaut obligatoire
                        'start_date' => now()->format('Y-m-d'), // Défaut obligatoire
                        'end_date' => now()->format('Y-m-d'),   // Défaut obligatoire
                        'contact_phones' => [['phone' => '0000000000']], // Tableau JSON obligatoire
                        'ticket_dispatch_method' => 'autre',
                        'ticket_dispatch_other' => 'À définir',
                    ]);

                    FolderHistory::create([
                        'folder_id' => $folder->id,
                        'user_id' => $user->id,
                        'action' => 'Création',
                        'changes_payload' => ['summary' => "Ouverture d'une nouvelle conversation / demande depuis le formulaire de contact."],
                    ]);
                }

                if ($folder) {
                    // On injecte le message dans le chat du dossier (en évitant le mass-assignment error)
                    $msg = new FolderMessage([
                        'folder_id' => $folder->id,
                        'user_id' => $user->id,
                        'message' => $data['message'],
                    ]);
                    $msg->is_action_required = true;
                    $msg->save();

                    // Notifications Admin
                    $setting = Setting::first();
                    $adminEmails = [];
                    
                    if ($setting && !empty($setting->admin_email_notifications)) {
                        $adminEmails = explode(',', $setting->admin_email_notifications);
                        $adminEmails = array_map('trim', $adminEmails);
                        $adminEmails = array_filter($adminEmails);
                    }
                    
                    if (empty($adminEmails)) {
                        $adminEmails = [env('MAIL_ADMIN_RECEIVER', env('MAIL_FROM_ADDRESS'))];
                    }
                    
                    if (!empty($adminEmails)) {
                        Mail::to($adminEmails)->send(new NewChatMessageForAdminMail($folder));
                    }
                }

                Notification::make()
                    ->title(__('Message envoyé avec succès !'))
                    ->success()
                    ->send();

                // Redirection directe vers la conversation
                $this->redirect(\App\Filament\Agency\Resources\AgencyFolderResource::getUrl('edit', ['record' => $folder->id]));

            } catch (\Exception $e) {
                Log::error('Erreur Formulaire Contact : ' . $e->getMessage());
                
                // 💡 ON AFFICHE L'ERREUR EXACTE A L'ÉCRAN
                Notification::make()
                    ->title(__('Erreur lors de la création'))
                    ->body($e->getMessage()) // Permettra d'identifier le champ manquant instantanément
                    ->danger()
                    ->persistent()
                    ->send();
            }
        } else {
            // Logique de secours pour les visiteurs non connectés
            try {
                Mail::raw("Nouvelle demande B2B (Visiteur non connecté)\n\n"
                    . "Nom : {$data['name']}\nAgence : {$data['agency_name']}\n"
                    . "Sujet : {$data['subject']}\n\nMessage :\n{$data['message']}", 
                    function ($message) use ($data) {
                        $message->to('resa@kansai-guide.com')->subject('Demande Portail : ' . $data['subject'])->replyTo($data['email']);
                    }
                );

                Notification::make()->title(__('Message envoyé !'))->success()->send();
                $this->form->fill(['name' => $data['name'], 'email' => $data['email'], 'agency_name' => $data['agency_name'], 'subject' => '', 'message' => '']);
            } catch (\Exception $e) {
                Notification::make()->title(__('Erreur d\'envoi'))->body($e->getMessage())->danger()->send();
            }
        }
    }
}