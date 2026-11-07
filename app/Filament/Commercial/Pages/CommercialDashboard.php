<?php

namespace App\Filament\Commercial\Pages;

use Filament\Pages\Dashboard;

/** Accueil de l'espace commercial : indicateurs et dossiers à suivre. */
class CommercialDashboard extends Dashboard
{
    protected static ?string $title = 'Tableau de bord';

    public function getColumns(): int|array
    {
        return 1;
    }
}
