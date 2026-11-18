<?php

namespace App\Console\Commands;

use App\Support\TransportFareImporter;
use Illuminate\Console\Command;

class ImportTransportFares extends Command
{
    protected $signature = 'transport-fares:import {path : Modèle Excel rempli (modele-grille-tarifs-trains.xlsx)} {--dry-run : Analyse sans rien enregistrer} {--kind= : train ou bus (détecté d'après les onglets si omis)}';

    protected $description = 'Importe la grille tarifaire des billets de train (remplace le précédent import Excel de la même famille train ou bus, garde les saisies manuelles)';

    public function handle(TransportFareImporter $importer): int
    {
        $kind = $this->option('kind');
        if ($kind !== null && ! in_array($kind, [TransportFareImporter::KIND_TRAIN, TransportFareImporter::KIND_BUS], true)) {
            $this->error('--kind doit valoir train ou bus.');

            return self::FAILURE;
        }
        $result = $importer->import($this->argument('path'), (bool) $this->option('dry-run'), $kind);

        $this->info(($this->option('dry-run') ? '[À blanc] ' : '') . "[{$result['kind']}] {$result['rows']} trajets avec prix → {$result['fares']} tarifs, {$result['seasons']} périodes de saison.");
        foreach ($result['skipped'] as $row => $reason) {
            $this->warn("Ligne {$row} ignorée : {$reason}");
        }

        return self::SUCCESS;
    }
}
