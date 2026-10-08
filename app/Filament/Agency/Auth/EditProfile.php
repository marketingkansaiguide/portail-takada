<?php

namespace App\Filament\Agency\Auth;

use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Page « Mon compte » du portail agences : l'utilisateur modifie son nom et son mot de passe.
 * L'adresse e-mail (identifiant de connexion) reste gérée par l'équipe Takada.
 */
class EditProfile extends BaseEditProfile
{
    public static function getLabel(): string
    {
        return __('Mon compte');
    }

    public function getTitle(): string | Htmlable
    {
        return __('Mon compte');
    }

    public static function isSimple(): bool
    {
        return false;
    }

    protected function getCurrentPasswordFormComponent(): Component
    {
        // Sans champ e-mail, le mot de passe actuel n'est demandé que lors d'un changement de mot de passe.
        return parent::getCurrentPasswordFormComponent()
            ->visible(fn (Get $get): bool => filled($get('password')));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getNameFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
                $this->getCurrentPasswordFormComponent(),
            ]);
    }
}
