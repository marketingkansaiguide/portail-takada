<?php

namespace App\Filament\Pages;

class Dashboard extends \Filament\Pages\Dashboard
{
    /**
     * Force le tableau de bord à créer une grille de 3 colonnes de largeur égale.
     */
    public function getColumns(): int | array
    {
        return 3;
    }
}