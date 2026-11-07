<?php

namespace App\Http\Middleware;

use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Authentification du panel d'administration : un commercial qui arrive sur /admin
 * (lien ancien, favori, mauvaise page de connexion) est envoyé vers son espace dédié
 * au lieu d'une page « accès refusé ». Tous les autres cas sont inchangés.
 */
class RedirectCommercialToOwnSpace extends Authenticate
{
    protected function authenticate($request, array $guards): void
    {
        $user = Filament::auth()->user();

        if ($user
            && method_exists($user, 'canAccessAdminPanel')
            && ! $user->canAccessAdminPanel()
            && $user->canAccessPanel(Filament::getPanel('commercial'))) {
            throw new HttpResponseException(redirect('/commercial'));
        }

        parent::authenticate($request, $guards);
    }
}
