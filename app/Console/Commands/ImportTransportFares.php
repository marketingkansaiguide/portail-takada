<?php

namespace App\Console\Commands;

use App\Support\TransportFareImporter;
use Illuminate\Console\Command;

class ImportTransportFares extends Command
{
    protected $signature = 'transport-fares:import {path : Modèle Excel rempli (modele-grille-tarifs-trains.xlsx)} {--dry-run : Analyse sans rien enregistrer}';

    protected $description = 'Importe la grille tarifaire des billets de train (remplace le précédent import Excel, garde les saisies manuelles)';

    public function handle(TransportFareImporter $importer): int
    {
        $result = $importer->import($this->argument('path'), (bool) $this->option('dry-run'));

        $this->info(($this->option('dry-run') ? '[À blanc] ' : '') . "{$result['rows']} trajets avec prix → {$result['fares']} tarifs, {$result['seasons']} périodes de saison.");
        foreach ($result['skipped'] as $row => $reason) {
            $this->warn("Ligne {$row} ignorée : {$reason}");
        }

        return self::SUCCESS;
    }
}
