<?php

namespace App\Filament\Pages;

use App\Support\AdminAccess;

class Dashboard extends \Filament\Pages\Dashboard
{
    public static function canAccess(): bool
    {
        return AdminAccess::allows('dashboard.view');
    }

    /**
     * Force le tableau de bord à créer une grille de 3 colonnes de largeur égale.
     */
    public function getColumns(): int | array
    {
        return 3;
    }
}
